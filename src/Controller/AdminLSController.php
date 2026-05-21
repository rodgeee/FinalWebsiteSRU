<?php

namespace App\Controller;

use App\Entity\Adminuser;
use App\Service\GoogleOAuthSettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AdminLSController extends AbstractController
{
    #[Route('/adminls/login', name: 'adminls_login', methods: ['GET','POST'])]
    #[Route('/admin/login', name: 'adminls_login_alias', methods: ['GET','POST'])]
    public function login(AuthenticationUtils $authenticationUtils, Request $request, GoogleOAuthSettings $googleOAuth): Response
    {
        // When accessed through ngrok over plain HTTP, force HTTPS to avoid losing secure cookies/CSRF tokens.
        if (!$request->isSecure() && str_contains($request->getHost(), 'ngrok')) {
            $httpsUrl = 'https://' . $request->getHttpHost() . $request->getRequestUri();
            return $this->redirect($httpsUrl, Response::HTTP_TEMPORARY_REDIRECT);
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();
        
        return $this->render('adminls/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'google_login_enabled' => $googleOAuth->isConfigured(),
        ]);
    }

    #[IsGranted('ROLE_STAFF')]
    #[Route('/post-login', name: 'post_login_redirect', methods: ['GET'])]
    public function postLoginRedirect(Security $security): Response
    {
        if ($security->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->redirectToRoute('admin_orders_index');
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/adminls/register', name: 'adminls_register', methods: ['GET','POST'])]
    public function register(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher, ValidatorInterface $validator): Response
    {
        $error = null;
        $formData = [
            'fullName' => '',
            'email' => '',
            'password' => '',
            'confirmPassword' => ''
        ];
        
        // Handle registration form submission
        if ($request->isMethod('POST')) {
            $fullName = $request->request->get('fullName');
            $email = $request->request->get('email');
            $password = $request->request->get('password');
            $confirmPassword = $request->request->get('confirmPassword');
            
            // Store form data for potential re-display
            $formData = [
                'fullName' => $fullName,
                'email' => $email,
                'password' => $password,
                'confirmPassword' => $confirmPassword
            ];
            
            // Validation using Symfony Validator
            $user = new Adminuser();
            $user->setFullname($fullName);
            $user->setEmail($email);
            $user->setPlainPassword($password);

            // Basic manual checks first
            if ($password !== $confirmPassword) {
                $error = 'Passwords do not match.';
            }

            if ($error === null) {
                $violations = $validator->validate($user, null, ['Default', 'Registration']);
                if (count($violations) > 0) {
                    $error = $violations[0]->getMessage();
                }
            }

            if ($error === null) {
                // Check if user already exists
                $existingUser = $entityManager->getRepository(Adminuser::class)->findOneBy(['Email' => $email]);
                
                if ($existingUser) {
                    $error = 'An account with this email already exists.';
                } else {
                    // Hash the password
                    $hashedPassword = $passwordHasher->hashPassword($user, $password);
                    $user->setPassword($hashedPassword);

                    // Save to database
                    try {
                        $entityManager->persist($user);
                        $entityManager->flush();

                        // Use PRG pattern with flash message; redirect to login page (303 for POST → GET)
                        $this->addFlash('success', 'Admin account created successfully. You can log in now — admin accounts do not require email verification.');
                        return $this->redirectToRoute('adminls_login', [], Response::HTTP_SEE_OTHER);
                    } catch (\Throwable $e) {
                        $error = 'Failed to create account. Please try again later.';
                    }
                }
            }
        }
        
        return $this->render('adminls/register.html.twig', [
            'error' => $error,
            'formData' => $formData
        ]);
    }

    #[Route('/adminls/test', name: 'adminls_test')]
    public function test(): Response
    {
        return new Response('Test route is working!');
    }

    #[Route('/adminls/logout', name: 'adminls_logout', methods: ['POST'])]
    public function logout(): Response
    {
        // This method can be blank - it will be intercepted by the logout key on your firewall
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
