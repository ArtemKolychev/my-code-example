<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ServiceCredentialType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['show_login']) {
            $builder->add('login', TextType::class, [
                'label' => 'Login / Email',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter your login',
                ],
                'constraints' => [new NotBlank()],
            ]);
        }

        $builder
            ->add('password', $options['plain_password'] ? TextType::class : PasswordType::class, [
                'label' => 'Password',
                'required' => !$options['has_existing_credential'],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => $options['has_existing_credential']
                        ? 'Leave empty to keep current password'
                        : 'Enter your password',
                ],
                'constraints' => $options['has_existing_credential'] ? [] : [new NotBlank()],
            ])
            ->add('save', SubmitType::class, [
                'label' => $options['has_existing_credential'] ? 'Update Credentials' : 'Save Credentials',
                'attr' => [
                    'class' => 'btn btn-primary',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'has_existing_credential' => false,
            'plain_password' => false,
            'show_login' => true,
        ]);
    }
}
