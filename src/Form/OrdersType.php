<?php

namespace App\Form;

use App\Entity\Orders;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrdersType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('OrderStatus', ChoiceType::class, [
                'choices' => [
                    'Pending' => 'Pending',
                    'Processing' => 'Processing',
                    'Shipped' => 'Shipped',
                    'Delivered' => 'Delivered',
                    'Cancelled' => 'Cancelled',
                ],
                'label' => 'Order Status'
            ])
            ->add('TrackingNumber', TextType::class, [
                'required' => false,
                'label' => 'Tracking Number'
            ])
            ->add('Remarks', TextareaType::class, [
                'required' => false,
                'label' => 'Remarks'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Orders::class,
        ]);
    }
}


