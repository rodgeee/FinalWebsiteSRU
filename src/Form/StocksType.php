<?php

namespace App\Form;

use App\Entity\Products;
use App\Entity\Stocks;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\File as FileConstraint;

final class StocksType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('Product', EntityType::class, [
                'class' => Products::class,
                'choice_label' => function(Products $product) {
                    return $product->getName() . ' - ' . $product->getColor() . ' (' . $product->getSize() . ')';
                },
                'placeholder' => 'Select existing product (or fill form below to create new)',
                'required' => false,
                'mapped' => false,
                'label' => 'Existing Product',
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700 mb-1'],
                'attr' => [
                    'class' => 'w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-all'
                ],
            ])
            // Product fields (for creating new product)
            ->add('productName', TextType::class, [
                'label' => 'Product Name',
                'required' => false,
                'mapped' => false,
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700 mb-1'],
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => 'Enter product name',
                    'class' => 'w-full rounded-xl border border-gray-300 px-3 py-2 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-all'
                ],
            ])
            ->add('productColor', TextType::class, [
                'label' => 'Color',
                'required' => false,
                'mapped' => false,
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700 mb-1'],
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => 'Enter color',
                    'class' => 'w-full rounded-xl border border-gray-300 px-3 py-2 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-all'
                ],
            ])
            ->add('productSize', TextType::class, [
                'label' => 'Size Range',
                'required' => false,
                'mapped' => false,
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700 mb-1'],
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => 'e.g. US7 - US14',
                    'class' => 'w-full rounded-xl border border-gray-300 px-3 py-2 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-all'
                ],
            ])
            ->add('productPrice', TextType::class, [
                'label' => 'Price',
                'required' => false,
                'mapped' => false,
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700 mb-1'],
                'attr' => [
                    'inputmode' => 'decimal',
                    'placeholder' => 'e.g. 11,000',
                    'class' => 'w-full rounded-xl border border-gray-300 px-3 py-2 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-all pl-7'
                ],
            ])
            ->add('productDescription', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'mapped' => false,
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700 mb-1'],
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Enter product description',
                    'class' => 'w-full rounded-xl border border-gray-300 px-3 py-2 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-all'
                ],
            ])
            ->add('productImages', FileType::class, [
                'label' => 'Product Images',
                'required' => false,
                'mapped' => false,
                'multiple' => true,
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700 mb-1'],
                'constraints' => [
                    new Count([
                        'max' => 4,
                        'maxMessage' => 'You can upload up to {{ limit }} images per product.',
                    ]),
                    new All([
                        'constraints' => [
                            new FileConstraint([
                                'maxSize' => '5M',
                                'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                                'mimeTypesMessage' => 'Please upload PNG, JPG, or WEBP images only.',
                            ]),
                        ],
                    ]),
                ],
                'attr' => [
                    'accept' => 'image/png,image/jpeg,image/webp',
                    'class' => 'hidden-file-input',
                ],
            ])
            // Stock fields
            ->add('Quantity', IntegerType::class, [
                'label' => 'Stock Quantity',
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700 mb-1'],
                'attr' => [
                    'min' => 0,
                    'class' => 'w-full rounded-xl border border-gray-300 px-3 py-2 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-all'
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Stocks::class,
        ]);
    }
}

