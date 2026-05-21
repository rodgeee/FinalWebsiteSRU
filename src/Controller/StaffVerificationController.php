<?php

namespace App\Controller;

use App\Service\StaffVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StaffVerificationController extends AbstractController
{
    #[Route('/staff/verify-email', name: 'staff_verify_email', methods: ['GET'])]
    public function verifyStaffEmail(
        Request $request,
        StaffVerificationService $staffVerificationService
    ): Response {
        $token = $request->query->get('token');

        if (!$token) {
            $this->addFlash('error', 'Staff verification token is missing.');

            return $this->redirectToRoute('adminls_login');
        }

        $staff = $staffVerificationService->verifyToken((string) $token);

        if (!$staff) {
            $this->addFlash('error', 'Invalid or expired staff verification token.');

            return $this->redirectToRoute('adminls_login');
        }

        $this->addFlash('success', 'Your staff account email has been verified. You can now log in.');

        return $this->redirectToRoute('adminls_login');
    }
}

