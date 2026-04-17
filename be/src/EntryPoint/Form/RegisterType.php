<?php

declare(strict_types=1);

namespace App\EntryPoint\Form;

use App\Application\Service\UserRegistrationService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @extends AbstractType<array<string, mixed>>
 */
final class RegisterType extends AbstractType
{
    public function __construct(
        private readonly UserRegistrationService $userRegistrationService,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'constraints' => [
                    new NotBlank(message: 'Please enter an email'),
                    new Callback(function ($object, $context): void {
                        if ($context instanceof ExecutionContextInterface && is_string($object) && $this->userRegistrationService->isEmailTaken($object)) {
                            $context->buildViolation('This email is already in use.')
                                ->addViolation();
                        }
                    }),
                ],
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'The password fields must match.',
                'options' => ['attr' => ['class' => 'form-control']],
                'required' => true,
                'first_options' => ['label' => 'Password'],
                'second_options' => ['label' => 'Repeat Password'],
                'constraints' => [
                    new NotBlank(message: 'Please enter a password'),
                    new Length(min: 6, minMessage: 'Your password should be at least {{ limit }} characters', max: 4096),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'label' => false,
                'mapped' => false,
                'constraints' => [
                    new IsTrue(message: 'Musíte souhlasit s podmínkami použití a zásadami ochrany osobních údajů.'),
                ],
            ])
            ->add('register', SubmitType::class, [
                'attr' => ['class' => 'btn btn-lg btn-primary'],
            ]);
    }
}
