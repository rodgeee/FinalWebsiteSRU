<?php

namespace App\Service\Api;

use App\Entity\Customer;
use App\Entity\CustomerAddress;
use App\Entity\Orders;
use App\Entity\Products;
use App\Entity\Services;
use App\Service\Product\ProductGalleryBuilder;
use App\Service\Product\ProductImageUrlResolver;
use App\Service\ServiceWorkflow;

final class CustomerResourceSerializer
{
    public function __construct(
        private readonly ProductGalleryBuilder $productGalleryBuilder,
        private readonly ProductImageUrlResolver $productImageUrlResolver,
    ) {
    }
    /**
     * @return array<string, mixed>
     */
    public function profile(Customer $customer): array
    {
        return [
            'id' => $customer->getId(),
            'fullName' => $customer->getFullName(),
            'email' => $customer->getEmail(),
            'phoneNumber' => $customer->getPhoneNumber(),
            'shoeSize' => $customer->getShoeSize(),
            'isVerified' => $customer->isVerified(),
            'createdAt' => $customer->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function product(Products $product, int $availableStock): array
    {
        $galleryPaths = $this->productGalleryBuilder->buildGalleryPaths($product);
        $primary = $product->getPrimaryImage();

        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'color' => $product->getColor(),
            'size' => $product->getSize(),
            'price' => $product->getPrice(),
            'description' => $product->getDescription(),
            'image' => $primary,
            'imageUrl' => $this->productImageUrlResolver->resolve($primary),
            'images' => $product->getImages(),
            'imageUrls' => $this->productImageUrlResolver->resolveMany($product->getImages()),
            'gallery' => $galleryPaths,
            'galleryUrls' => $this->productImageUrlResolver->resolveMany($galleryPaths),
            'availableStock' => $availableStock,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function orderSummary(Orders $order): array
    {
        return [
            'id' => $order->getId(),
            'orderNumber' => $order->getDisplayOrderNumber(),
            'orderStatus' => $order->getOrderStatus(),
            'paymentMethod' => $order->getPaymentMethod(),
            'totalPrice' => $order->getTotalPrice(),
            'quantity' => $order->getQuantity(),
            'dateCreated' => $order->getDateCreated()?->format(\DateTimeInterface::ATOM),
            'trackingNumber' => $order->getTrackingNumber(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function orderDetail(Orders $order): array
    {
        $products = [];
        foreach ($order->getProducts() as $product) {
            $primary = $product->getPrimaryImage();
            $products[] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'color' => $product->getColor(),
                'size' => $product->getSize(),
                'price' => $product->getPrice(),
                'image' => $primary,
                'imageUrl' => $this->productImageUrlResolver->resolve($primary),
            ];
        }

        return array_merge($this->orderSummary($order), [
            'products' => $products,
            'payment' => [
                'method' => $order->getPaymentMethod(),
                'totalPrice' => $order->getTotalPrice(),
                'status' => $order->getOrderStatus(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function address(CustomerAddress $address): array
    {
        return [
            'id' => $address->getId(),
            'label' => $address->getLabel(),
            'addressLine1' => $address->getAddressLine1(),
            'addressLine2' => $address->getAddressLine2(),
            'city' => $address->getCity(),
            'province' => $address->getProvince(),
            'postalCode' => $address->getPostalCode(),
            'country' => $address->getCountry(),
            'contactEmail' => $address->getContactEmail(),
            'contactPhone' => $address->getContactPhone(),
            'isDefault' => $address->isDefault(),
            'displayLine1' => $address->getDisplayLine1(),
            'displayLine2' => $address->getDisplayLine2(),
            'createdAt' => $address->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serviceBookingSummary(Services $service): array
    {
        $status = $service->getStatus() ?: ServiceWorkflow::DEFAULT_STATUS;
        $meta = ServiceWorkflow::stageMeta()[$status] ?? ServiceWorkflow::stageMeta()[ServiceWorkflow::DEFAULT_STATUS];

        return [
            'id' => $service->getId(),
            'shoeName' => $service->getShoeName(),
            'packageName' => $service->getServiceType(),
            'status' => $status,
            'progress' => $meta['progress'],
            'phase' => $meta['phase'],
            'note' => $service->getNote(),
            'createdAt' => $service->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $service->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serviceBookingDetail(Services $service): array
    {
        return $this->serviceBookingSummary($service);
    }
}
