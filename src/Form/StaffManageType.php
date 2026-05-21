<?php

namespace App\Form;

use App\Entity\Staff;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType as SelectType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class StaffManageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $requirePassword = (bool) $options['require_password'];
        $currentRoles = (array) $options['current_roles'];
        $hasAdmin = in_array('ROLE_ADMIN', $currentRoles, true);
        $currentStatus = (string) ($options['current_status'] ?? 'active');

        $builder
            ->add('fullName', TextType::class, [
                'label' => 'Full name',
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
            ])
            ->add('roleChoice', ChoiceType::class, [
                'label' => 'Role',
                'mapped' => false,
                'choices' => [
                    'Admin (full access)' => 'ROLE_ADMIN',
                    'Staff' => 'ROLE_STAFF',
                ],
                'data' => $hasAdmin ? 'ROLE_ADMIN' : 'ROLE_STAFF',
                'required' => true,
            ])
            ->add('statusChoice', SelectType::class, [
                'label' => 'Account status',
                'mapped' => false,
                'choices' => [
                    'Active' => 'active',
                    'Disabled' => 'disabled',
                    'Archived' => 'archived',
                ],
                'data' => $currentStatus,
                'required' => true,
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => $requirePassword,
                'first_options' => [
                    'label' => $requirePassword ? 'Password' : 'New password (optional)',
                ],
                'second_options' => [
                    'label' => $requirePassword ? 'Confirm password' : 'Confirm new password',
                ],
                'invalid_message' => 'The password fields must match.',
                'constraints' => $requirePassword ? [
                    new Assert\NotBlank(message: 'Password is required.'),
                    new Assert\Length(min: 8, minMessage: 'Password must be at least {{ limit }} characters long.'),
                    new Assert\Regex(
                        pattern: "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^A-Za-z0-9]).+$/",
                        message: 'Password must include upper, lower, number, and symbol.'
                    ),
                ] : [],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Staff::class,
            'require_password' => false,
            'current_roles' => [],
            'current_status' => 'active',
        ]);

        $resolver->setAllowedTypes('require_password', 'bool');
        $resolver->setAllowedTypes('current_roles', 'array');
        $resolver->setAllowedTypes('current_status', 'string');
    }
}

