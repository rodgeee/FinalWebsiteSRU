<?php

namespace App\Form;

use App\Entity\Products;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProductsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('Name', TextType::class, [
                'label' => 'Name',
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700 mb-1'],
                'attr' => [
                    'maxlength' => 255,
                    'class' => 'w-full rounded-xl border border-gray-300 px-3 py-2 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-all'
                ],
            ])
            ->add('Color', TextType::class, [
                'label' => 'Color',
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700 mb-1'],
                'attr' => [
                    'maxlength' => 255,
                    'class' => 'w-full rounded-xl border border-gray-300 px-3 py-2 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-all'
                ],
            ])
            ->add('Size', TextType::class, [
                'label' => 'Size',
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700 mb-1'],
                'attr' => [
                    'maxlength' => 255,
                    'class' => 'w-full rounded-xl border border-gray-300 px-3 py-2 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-all'
                ],
            ])
            ->add('Stocks', IntegerType::class, [
                'label' => 'Stocks',
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700 mb-1'],
                'attr' => [
                    'min' => 0,
                    'class' => 'appearance-none w-full rounded-xl border border-gray-300 px-3 py-2 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-all'
                ],
            ])
            ->add('Price', MoneyType::class, [
                'label' => 'Price',
                'currency' => 'USD',
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700 mb-1'],
                'attr' => [
                    'class' => 'w-full rounded-xl border border-gray-300 px-3 py-2 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-all'
                ],
                'scale' => 2,
            ])
            ->add('Description', TextareaType::class, [
                'label' => 'Description',
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700 mb-1'],
                'attr' => [
                    'rows' => 4,
                    'class' => 'w-full rounded-xl border border-gray-300 px-3 py-2 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-all'
                ],
            ])
            ->add('Image', TextType::class, [
                'label' => 'Image URL',
                'required' => false,
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700 mb-1'],
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => 'https://example.com/image.jpg',
                    'class' => 'w-full rounded-xl border border-gray-300 px-3 py-2 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-all'
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Products::class,
        ]);
    }
}


