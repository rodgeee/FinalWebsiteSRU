<?php

namespace App\Controller;

use App\Repository\ProductsRepository;
use Doctrine\DBAL\Exception\ConnectionException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LandingpageController extends AbstractController
{
    
    #[Route('/landingpage', name: 'app_landingpage_legacy', methods: ['GET'])]
    public function index(Request $request, ProductsRepository $productsRepository): Response
    {
        // If accessed via ngrok (any ngrok host), send users directly to the admin login page.
        if ($this->isNgrokHost($request)) {
            return $this->redirectToRoute('adminls_login', [], Response::HTTP_SEE_OTHER);
        }

        try {
            $products = $productsRepository->findAll();
        } catch (ConnectionException $e) {
            // If database connection fails, show empty products array
            $products = [];
        } catch (\Exception $e) {
            // Catch any other database-related errors
            $products = [];
        }

        return $this->render('landingpage/index.html.twig', [
            'controller_name' => 'LandingpageController',
            'products' => $products,
        ]);
    }

    /**
     * Detect ngrok by checking common forwarded/host headers to handle proxy setups.
     */
    private function isNgrokHost(Request $request): bool
    {
        $candidates = [
            $request->headers->get('x-forwarded-host'),
            $request->headers->get('x-original-host'),
            $request->headers->get('host'),
            $request->getHost(),
        ];

        foreach ($candidates as $host) {
            if ($host && str_contains($host, 'ngrok')) {
                return true;
            }
        }

        $forwarded = $request->headers->get('forwarded');
        if ($forwarded && str_contains($forwarded, 'ngrok')) {
            return true;
        }

        return false;
    }
}
