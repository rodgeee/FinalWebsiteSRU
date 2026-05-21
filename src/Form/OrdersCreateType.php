<?php

namespace App\Form;

use App\Entity\Orders;
use App\Entity\Products;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class OrdersCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('CustomerName', TextType::class, [
                'label' => 'Customer Name',
                'attr' => [
                    'class' => 'mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none',
                    'maxlength' => 255,
                ],
            ])
            ->add('Email', EmailType::class, [
                'label' => 'Customer Email',
                'attr' => [
                    'class' => 'mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none',
                ],
            ])
            ->add('Products', EntityType::class, [
                'class' => Products::class,
                'choice_label' => static fn (Products $product): string => sprintf(
                    '%s — %s (%s)',
                    $product->getName() ?? 'Product',
                    $product->getColor() ?? 'Color',
                    $product->getSize() ?? 'Size'
                ),
                'choice_attr' => static fn (Products $product): array => [
                    'data-price' => $product->getPrice(),
                ],
                'multiple' => true, // matches ManyToMany on Orders.Products
                'expanded' => false,
                'required' => true,
                'label' => 'Products',
                'attr' => [
                    'class' => 'mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none',
                    'data-product-select' => '1',
                ],
                'placeholder' => 'Select one or more products',
            ])
            ->add('Quantity', IntegerType::class, [
                'label' => 'Quantity',
                'attr' => [
                    'class' => 'mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none',
                    'min' => 1,
                ],
            ])
            ->add('TotalPrice', MoneyType::class, [
                'label' => 'Total Price',
                'currency' => 'PHP',
                'divisor' => 1,
                'attr' => [
                    'class' => 'mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none',
                    'inputmode' => 'decimal',
                    'data-total-price' => '1',
                    'readonly' => true,
                ],
            ])
            ->add('PaymentMethod', ChoiceType::class, [
                'label' => 'Payment Method',
                'choices' => [
                    'Cash on Delivery' => 'COD',
                    'GCash' => 'GCash',
                    'Bank Transfer' => 'Bank Transfer',
                    'Card' => 'Card',
                ],
                'attr' => [
                    'class' => 'mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none bg-white',
                ],
            ])
            ->add('OrderStatus', ChoiceType::class, [
                'label' => 'Order Status',
                'choices' => [
                    'Pending' => 'Pending',
                    'Processing' => 'Processing',
                    'Shipped' => 'Shipped',
                    'Delivered' => 'Delivered',
                    'Cancelled' => 'Cancelled',
                ],
                'data' => 'Pending',
                'attr' => [
                    'class' => 'mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none bg-white',
                ],
            ])
            ->add('TrackingNumber', TextType::class, [
                'label' => 'Tracking Number',
                'required' => false,
                'attr' => [
                    'class' => 'mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none',
                    'maxlength' => 100,
                ],
            ])
            ->add('Remarks', TextareaType::class, [
                'label' => 'Remarks',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'class' => 'mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-brand focus:outline-none',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Orders::class,
        ]);
    }
}


