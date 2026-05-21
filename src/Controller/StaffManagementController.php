<?php

namespace App\Controller;

use App\Entity\Staff;
use App\Entity\Adminuser;
use App\Form\StaffManageType;
use App\Repository\AdminuserRepository;
use App\Repository\StaffRepository;
use App\Service\StaffVerificationService;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/staff', name: 'admin_staff_')]
final class StaffManagementController extends AbstractController
{
    /**
     * @param Staff[] $staff
     * @return Staff[]
     */
    private function filterNonAdmin(array $staff): array
    {
        return array_values(array_filter(
            $staff,
            static fn (Staff $user): bool => !in_array('ROLE_ADMIN', $user->getRoles(), true)
        ));
    }

    /**
     * @param Staff[] $staff
     */
    private function countActive(array $staff): int
    {
        return count(array_filter($staff, static fn (Staff $user): bool => $user->isActive()));
    }

    /**
     * Build a unified admin list from Adminuser entities and Staff records that carry ROLE_ADMIN.
     *
     * @return array<int, array{name:string,email:string,createdAt:\DateTimeInterface|null,source:string}>
     */
    private function buildAdminList(AdminuserRepository $adminuserRepository, StaffRepository $staffRepository): array
    {
        $admins = [];

        foreach ($adminuserRepository->findBy([], ['id' => 'DESC']) as $admin) {
            $admins[] = [
                'name' => $admin->getFullname(),
                'email' => $admin->getEmail(),
                'createdAt' => $admin->getCreatedAt() ?? null,
                'source' => 'admin',
            ];
        }

        foreach ($staffRepository->findBy([], ['id' => 'DESC']) as $staff) {
            if (!in_array('ROLE_ADMIN', $staff->getRoles(), true)) {
                continue;
            }
            $admins[] = [
                'name' => $staff->getFullName(),
                'email' => $staff->getEmail(),
                'createdAt' => $staff->getCreatedAt() ?? null,
                'source' => 'staff',
            ];
        }

        return $admins;
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(StaffRepository $staffRepository, AdminuserRepository $adminuserRepository): Response
    {
        $staff = new Staff();
        $form = $this->createForm(StaffManageType::class, $staff, [
            'require_password' => true,
            'current_roles' => $staff->getRoles(),
            'current_status' => $staff->getStatus(),
        ]);

        $allStaff = $staffRepository->findBy([], ['id' => 'DESC']);
        $staffList = $this->filterNonAdmin($allStaff);
        $activeStaff = $this->countActive($staffList);

        return $this->render('staff/index.html.twig', [
            'staff' => $staffList,
            'admins' => $this->buildAdminList($adminuserRepository, $staffRepository),
            'activeStaff' => $activeStaff,
            'stats' => [
                'staff' => count($staffList),
                'admins' => count($this->buildAdminList($adminuserRepository, $staffRepository)),
            ],
            'form' => $form,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ActivityLogger $activityLogger,
        StaffRepository $staffRepository,
        AdminuserRepository $adminuserRepository,
        StaffVerificationService $staffVerificationService
    ): Response {
        $staff = new Staff();
        $staff->setRoles(['ROLE_STAFF']);

        $form = $this->createForm(StaffManageType::class, $staff, [
            'require_password' => true,
            'current_roles' => $staff->getRoles(),
            'current_status' => $staff->getStatus(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $roleChoice = $form->get('roleChoice')->getData();
            $statusChoice = $form->get('statusChoice')->getData();

            if ($roleChoice === 'ROLE_ADMIN') {
                // Create a true Adminuser record
                $admin = new Adminuser();
                $admin->setFullname((string) $staff->getFullName());
                $admin->setEmail((string) $staff->getEmail());
                $hashedPassword = $passwordHasher->hashPassword($admin, (string) $plainPassword);
                $admin->setPassword($hashedPassword);
                $entityManager->persist($admin);
                $entityManager->flush();

                $this->addFlash('success', 'Admin account created. They can log in immediately — admin accounts do not use email verification.');
                $activityLogger->log(
                    'create',
                    'admin',
                    (string) $admin->getId(),
                    sprintf('Created admin account for %s.', $admin->getEmail()),
                    sprintf('Admin: %s (ID: %s)', $admin->getEmail(), $admin->getId()),
                    ['email' => $admin->getEmail(), 'name' => $admin->getFullname()]
                );
            } else {
                // Create a staff record
                $hashedPassword = $passwordHasher->hashPassword($staff, (string) $plainPassword);
                $staff->setPassword($hashedPassword);
                $staff->setRoles(['ROLE_STAFF']);
                // Staff must verify their email before being able to log in.
                $staff->setStatus('disabled');
                $staff->setIsActive(false);
                $staff->setIsVerified(false);

                $entityManager->persist($staff);
                $entityManager->flush();

                // Send verification email to the staff (Google) account.
                $verificationToken = $staffVerificationService->ensureVerificationToken($staff);
                $verificationUrl = $this->generateUrl(
                    'staff_verify_email',
                    ['token' => $verificationToken],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );
                $emailSent = $staffVerificationService->sendVerificationEmail($staff, $verificationUrl);

                if ($emailSent) {
                    $this->addFlash('success', 'Staff account created. A verification email was sent — they must verify before logging in.');
                } else {
                    $this->addFlash('warning', 'Staff account created, but the verification email could not be sent. Configure MAILER_DSN on Railway (SMTP). They can verify using this link: '.$verificationUrl);
                }
                $activityLogger->log(
                    'create',
                    'staff',
                    (string) $staff->getId(),
                    sprintf('Created staff account for %s.', $staff->getEmail()),
                    sprintf('Staff: %s (ID: %s)', $staff->getEmail(), $staff->getId()),
                    ['email' => $staff->getEmail(), 'name' => $staff->getFullName()]
                );
            }

            return $this->redirectToRoute('admin_staff_index', [], Response::HTTP_SEE_OTHER);
        }

        $allStaff = $staffRepository->findBy([], ['id' => 'DESC']);
        $staffList = $this->filterNonAdmin($allStaff);
        $activeStaff = $this->countActive($staffList);

        return $this->render('staff/index.html.twig', [
            'staff' => $staffList,
            'admins' => $this->buildAdminList($adminuserRepository, $staffRepository),
            'activeStaff' => $activeStaff,
            'stats' => [
                'staff' => count($staffList),
                'admins' => count($this->buildAdminList($adminuserRepository, $staffRepository)),
            ],
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Staff $staff,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ActivityLogger $activityLogger,
        StaffVerificationService $staffVerificationService
    ): Response {
        $before = [
            'name' => $staff->getFullName(),
            'email' => $staff->getEmail(),
            'roles' => $staff->getRoles(),
            'active' => $staff->isActive(),
            'status' => $staff->getStatus(),
        ];

        $form = $this->createForm(StaffManageType::class, $staff, [
            'require_password' => false,
            'current_roles' => $staff->getRoles(),
            'current_status' => $staff->getStatus(),
        ]);
        $form->handleRequest($request);

        $plainPassword = $form->get('plainPassword')->getData();
        if ($form->isSubmitted() && $plainPassword !== null && $plainPassword !== '') {
            if (strlen((string) $plainPassword) < 8) {
                $form->get('plainPassword')->addError(new \Symfony\Component\Form\FormError('Password must be at least 8 characters long.'));
            } elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^A-Za-z0-9]).+$/", (string) $plainPassword)) {
                $form->get('plainPassword')->addError(new \Symfony\Component\Form\FormError('Password must include upper, lower, number, and symbol.'));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $roleChoice = $form->get('roleChoice')->getData();
            $staff->setRoles($roleChoice === 'ROLE_ADMIN' ? ['ROLE_ADMIN'] : ['ROLE_STAFF']);

            $statusChoice = $form->get('statusChoice')->getData();
                // Only mark active after verification; otherwise keep it disabled.
                if ($statusChoice === 'active') {
                    if ($staff->isVerified()) {
                        $staff->setStatus('active');
                        $staff->setIsActive(true);
                    } else {
                        // Admin chose "active" but the account isn't verified yet.
                        // Provision a verification token + resend email, but keep login blocked until verified.
                        $staffVerificationService->ensureVerificationToken($staff);
                        $verificationUrl = $this->generateUrl(
                            'staff_verify_email',
                            ['token' => (string) $staff->getVerificationToken()],
                            UrlGeneratorInterface::ABSOLUTE_URL
                        );
                        if (!$staffVerificationService->sendVerificationEmail($staff, $verificationUrl)) {
                            $this->addFlash('warning', 'Could not send verification email (check MAILER_DSN). Verification link: '.$verificationUrl);
                        }

                        $staff->setStatus('disabled');
                        $staff->setIsActive(false);
                    }
                } else {
                    $staff->setStatus($statusChoice);
                    $staff->setIsActive(false);
                }

            $passwordReset = false;
            if ($plainPassword !== null && $plainPassword !== '') {
                $hashedPassword = $passwordHasher->hashPassword($staff, (string) $plainPassword);
                $staff->setPassword($hashedPassword);
                $passwordReset = true;
            }

            $entityManager->flush();

            $changes = [];
            $addChange = static function (string $label, mixed $old, mixed $new) use (&$changes): void {
                if ($old !== $new) {
                    $changes[$label] = sprintf('%s → %s', $old ?? '—', $new ?? '—');
                }
            };
            $addChange('name', $before['name'], $staff->getFullName());
            $addChange('email', $before['email'], $staff->getEmail());
            $addChange('roles', implode(', ', $before['roles']), implode(', ', $staff->getRoles()));
            $addChange('active', $before['active'] ? 'active' : 'disabled', $staff->isActive() ? 'active' : 'disabled');
            $addChange('status', $before['status'], $staff->getStatus());
            if ($passwordReset) {
                $changes['password'] = 'reset';
            }

            $activityLogger->log(
                'update',
                'staff',
                (string) $staff->getId(),
                sprintf('Updated staff account %s.', $staff->getEmail()),
                sprintf('Staff: %s (ID: %s)', $staff->getEmail(), $staff->getId()),
                $changes
            );

            $this->addFlash('success', 'Staff account updated.');
            return $this->redirectToRoute('admin_staff_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('staff/edit.html.twig', [
            'staff' => $staff,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Staff $staff,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger
    ): Response {
        $token = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete'.$staff->getId(), (string) $token)) {
            $email = $staff->getEmail();
            $entityManager->remove($staff);
            $entityManager->flush();

            $activityLogger->log(
                'delete',
                'staff',
                (string) $staff->getId(),
                sprintf('Deleted staff account %s.', $email),
                sprintf('Staff: %s (ID: %s)', $email, $staff->getId())
            );

            $this->addFlash('success', 'Staff account deleted.');
        }

        return $this->redirectToRoute('admin_staff_index', [], Response::HTTP_SEE_OTHER);
    }
}


