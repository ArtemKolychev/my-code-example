<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'required' => true,
                'label' => 'Jméno (Name)',
                'constraints' => [new NotBlank(message: 'Jméno je povinné.')],
            ])
            ->add('address', TextType::class, [
                'required' => true,
                'label' => 'Address',
                'constraints' => [new NotBlank(message: 'Adresa je povinná.')],
            ])
            ->add('zip', TextType::class, [
                'required' => true,
                'label' => 'PSČ (ZIP)',
                'constraints' => [new NotBlank(message: 'PSČ je povinné.')],
            ])
            ->add('phone', TextType::class, [
                'required' => true,
                'label' => 'Phone',
                'constraints' => [new NotBlank(message: 'Telefon je povinný.')],
            ])
            ->add('save', SubmitType::class, ['label' => 'Save']);
    }
}
