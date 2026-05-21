<?php

namespace App\Controller;

use App\Entity\Adminuser;
use App\Entity\Staff;
use App\Form\ProfileType;
use App\Form\StaffProfileType;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\ActivityLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
final class ProfileController extends AbstractController
{
    #[Route('/dashboard/profile', name: 'app_profile')]
    public function show(): Response
    {
        $user = $this->getUser();
        
        if (!$user instanceof Adminuser && !$user instanceof Staff) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('profile/show.html.twig', [
            'user' => $user,
        ]);
    }

#[Route('/dashboard/profile/edit', name: 'app_profile_edit')]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ActivityLogger $activityLogger
    ): Response {
        $user = $this->getUser();
        
        if (!$user instanceof Adminuser && !$user instanceof Staff) {
            return $this->redirectToRoute('app_dashboard');
        }

        $formClass = $user instanceof Staff ? StaffProfileType::class : ProfileType::class;
        // Snapshot current values for change log
        $before = [
            'name' => $user instanceof Adminuser ? $user->getFullname() : $user->getFullName(),
            'email' => $user->getEmail(),
        ];

        $form = $this->createForm($formClass, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Handle password update if provided (plainPassword is unmapped, so get it from form)
            $plainPassword = $form->get('plainPassword')->getData();
            
            // Validate password if provided
                if ($plainPassword !== null && $plainPassword !== '') {
                    if (strlen($plainPassword) < 8) {
                        $form->get('plainPassword')->addError(new \Symfony\Component\Form\FormError('Password must be at least 8 characters long.'));
                    } elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^A-Za-z0-9]).+$/", $plainPassword)) {
                        $form->get('plainPassword')->addError(new \Symfony\Component\Form\FormError('Password must include upper, lower, number, and symbol.'));
                    } elseif ($passwordHasher->isPasswordValid($user, $plainPassword)) {
                        $error = new \Symfony\Component\Form\FormError('You cannot use the same password.');
                        $form->get('plainPassword')->addError($error);
                        // also surface on confirm field so users notice
                        if ($form->get('plainPassword')->has('second')) {
                            $form->get('plainPassword')->get('second')->addError(new \Symfony\Component\Form\FormError('You cannot use the same password.'));
                        }
                    }
                }
            
            if ($form->isValid()) {
                $passwordChanged = false;
                if ($plainPassword !== null && $plainPassword !== '') {
                    $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                    $user->setPassword($hashedPassword);
                    $passwordChanged = true;
                }

                $entityManager->flush();

                $changes = [];
                $addChange = static function (string $label, mixed $old, mixed $new) use (&$changes): void {
                    if ($old !== $new) {
                        $changes[$label] = sprintf('%s → %s', $old ?? '—', $new ?? '—');
                    }
                };

                $afterName = $user instanceof Adminuser ? $user->getFullname() : $user->getFullName();
                $afterEmail = $user->getEmail();
                $addChange('name', $before['name'], $afterName);
                $addChange('email', $before['email'], $afterEmail);
                if ($passwordChanged) {
                    $changes['password'] = 'updated';
                }

                $activityLogger->log(
                    'update',
                    'profile',
                    (string) $user->getId(),
                    sprintf('Updated profile for %s.', $user->getEmail() ?? 'user'),
                    sprintf('Profile: %s (ID: %s)', $user->getEmail() ?? 'user', $user->getId() ?? 'N/A'),
                    $changes
                );

                $this->addFlash('success', 'Profile updated successfully!');
                return $this->redirectToRoute('app_profile');
            }
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }

}

