<?php

declare(strict_types=1);

namespace App\EntryPoint\Form;

use App\Application\DTO\Payload\ArticleUpdatePayload;
use App\Domain\Enum\Category;
use App\Domain\Enum\Condition;
use App\EntryPoint\Form\DataTransformer\JsonStringTransformer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<ArticleUpdatePayload>
 */
final class EditArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', HiddenType::class)
            ->add('title', TextType::class, [
                'label' => 'Title',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter article title',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'Enter article description',
                ],
            ])
            ->add('price', NumberType::class, [
                'label' => 'Price',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter article price',
                    'step' => '0.01',
                ],
            ])
            ->add('category', EnumType::class, [
                'class' => Category::class,
                'required' => false,
                'placeholder' => 'Vyberte kategorii',
                'choice_label' => fn (Category $c): string => $c->label(),
                'label' => 'Kategorie',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('condition', EnumType::class, [
                'class' => Condition::class,
                'required' => false,
                'placeholder' => 'Vyberte stav',
                'choice_label' => fn (Condition $c): string => $c->label(),
                'label' => 'Stav',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('metaFields', HiddenType::class, [
                'required' => false,
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Save Changes',
                'attr' => [
                    'class' => 'btn btn-primary',
                ],
            ])
            ->add('images', FileType::class, [
                'label' => 'Upload Images',
                'multiple' => true,
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'accept' => 'image/jpg,image/jpeg,image/png',
                    'class' => 'form-control',
                ],
            ])
        ;

        $builder->get('metaFields')->addModelTransformer(new JsonStringTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ArticleUpdatePayload::class,
        ]);
    }
}
