<?php

namespace App\Form;

use App\Entity\CustomerAddress;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class CustomerCheckoutType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<CustomerAddress> $addresses */
        $addresses = $options['addresses'];

        $builder
            ->add('paymentMethod', ChoiceType::class, [
                'label' => 'Payment method',
                'choices' => [
                    'Cash on Delivery' => 'COD',
                    'GCash' => 'GCash',
                    'Bank Transfer' => 'Bank Transfer',
                    'Debit / Credit Card' => 'Card',
                ],
                'constraints' => [new NotBlank()],
                'attr' => ['class' => 'lp-checkout__select'],
            ])
            ->add('orderNotes', TextareaType::class, [
                'label' => 'Order notes (optional)',
                'required' => false,
                'attr' => [
                    'class' => 'lp-checkout__textarea',
                    'rows' => 3,
                    'placeholder' => 'Delivery instructions, gate code, etc.',
                ],
            ]);

        if ($addresses !== []) {
            $addressChoices = [];
            foreach ($addresses as $address) {
                $label = sprintf(
                    '%s — %s',
                    $address->getLabel() ?? 'Address',
                    $address->getDisplayLine1()
                );
                $addressChoices[$label] = (string) $address->getId();
            }

            $defaultId = null;
            foreach ($addresses as $address) {
                if ($address->isDefault()) {
                    $defaultId = (string) $address->getId();
                    break;
                }
            }
            if ($defaultId === null) {
                $defaultId = (string) $addresses[0]->getId();
            }

            $builder->add('addressId', ChoiceType::class, [
                'label' => 'Ship to',
                'choices' => $addressChoices,
                'data' => $defaultId,
                'expanded' => true,
                'constraints' => [new NotBlank(message: 'Please select a shipping address.')],
                'attr' => ['class' => 'lp-checkout__address-options'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'addresses' => [],
        ]);
        $resolver->setAllowedTypes('addresses', 'array');
    }
}
