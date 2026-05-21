<?php

namespace App\Form;

use App\Entity\Adminuser;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

final class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('Fullname', TextType::class, [
                'label' => 'Full Name',
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700 mb-1'],
                'attr' => [
                    'maxlength' => 255,
                    'class' => 'w-full rounded-xl border border-gray-300 px-3 py-2 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-all'
                ],
            ])
            ->add('Email', EmailType::class, [
                'label' => 'Email',
                'label_attr' => ['class' => 'block text-sm font-medium text-gray-700 mb-1'],
                'attr' => [
                    'maxlength' => 180,
                    'class' => 'w-full rounded-xl border border-gray-300 px-3 py-2 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-all'
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'required' => false,
                'first_options' => [
                    'label' => 'New Password (leave blank to keep current)',
                    'label_attr' => ['class' => 'block text-sm font-medium text-gray-700 mb-1'],
                    'attr' => [
                        'class' => 'w-full rounded-xl border border-gray-300 px-3 py-2 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-all',
                        'placeholder' => 'Enter new password'
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirm New Password',
                    'label_attr' => ['class' => 'block text-sm font-medium text-gray-700 mb-1'],
                    'attr' => [
                        'class' => 'w-full rounded-xl border border-gray-300 px-3 py-2 text-sm placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-all',
                        'placeholder' => 'Confirm new password'
                    ],
                ],
                'invalid_message' => 'The password fields must match.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Adminuser::class,
        ]);
    }
}


