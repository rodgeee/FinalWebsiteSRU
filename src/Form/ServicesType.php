<?php

namespace App\Form;

use App\Entity\Services;
use App\Service\ServiceWorkflow;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ServicesType extends AbstractType
{
    private const SERVICE_PACKAGES = [
        'Essential Clean' => 'Essential Clean',
        'Deep Clean' => 'Deep Clean',
        'Premium Restore' => 'Premium Restore',
        'Basic Cleaning' => 'Basic Cleaning',
        'Deep Cleaning' => 'Deep Cleaning',
        'Premium Repaint' => 'Premium Repaint',
        'Sole Swap & Repair' => 'Sole Swap & Repair',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ShoeName', TextType::class, [
                'label' => 'Shoe / Pair name',
                'label_attr' => ['class' => 'block text-sm font-semibold text-text mb-1'],
                'attr' => [
                    'maxlength' => 255,
                    'placeholder' => 'e.g. Air Jordan 1 Lost & Found',
                    'class' => 'w-full rounded-xl border border-border px-3 py-2 text-sm placeholder:text-textMuted focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-all',
                ],
            ])
            ->add('ServiceType', ChoiceType::class, [
                'label' => 'Service package',
                'placeholder' => 'Select package',
                'choices' => self::SERVICE_PACKAGES,
                'label_attr' => ['class' => 'block text-sm font-semibold text-text mb-1'],
                'attr' => [
                    'class' => 'w-full rounded-xl border border-border bg-white px-3 py-2 text-sm text-text focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-all',
                ],
                'choice_attr' => static fn () => [
                    'class' => 'text-text',
                ],
            ])
            ->add('Status', ChoiceType::class, [
                'label' => 'Current stage',
                'placeholder' => null,
                'choices' => ServiceWorkflow::statusChoices(),
                'label_attr' => ['class' => 'block text-sm font-semibold text-text mb-1'],
                'attr' => [
                    'class' => 'w-full rounded-xl border border-border bg-white px-3 py-2 text-sm text-text focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-all',
                ],
            ])
            ->add('Note', TextareaType::class, [
                'label' => 'Notes / Special Requests',
                'label_attr' => ['class' => 'block text-sm font-semibold text-text mb-1'],
                'attr' => [
                    'rows' => 3,
                    'maxlength' => 255,
                    'placeholder' => 'Smudged midsole, suede scuffs, rush for weekend event...',
                    'class' => 'w-full rounded-xl border border-border px-3 py-2 text-sm placeholder:text-textMuted focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-all',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Services::class,
        ]);
    }
}

