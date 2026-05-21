<?php

namespace App\Controller;

use App\Entity\Products;
use App\Form\ProductsType;
use App\Repository\ProductsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
#[Route('/admin/products', name: 'admin_products_')]
final class ProductsController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, ProductsRepository $productsRepository): Response
    {
        $search = $request->query->get('q');
        $sortField = $request->query->get('sort');
        $sortDir = $request->query->get('dir');

        return $this->render('products/index.html.twig', [
            'products' => $productsRepository->findForIndex($search, $sortField, $sortDir, null),
            'q' => $search,
            'sort' => $sortField,
            'dir' => $sortDir,
        ]);
    }

    // Products can only be created/updated through the Stocks page
    // This route has been removed - use /admin/stocks to manage products

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Products $product): Response
    {
        return $this->render('products/show.html.twig', [
            'product' => $product,
        ]);
    }

    // Products can only be edited/deleted through the Stocks page
    // This route has been removed - use /admin/stocks to manage products
}


