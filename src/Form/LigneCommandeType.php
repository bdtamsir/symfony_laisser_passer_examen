<?php

namespace App\Form;

use App\Entity\Article;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LigneCommandeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('article', EntityType::class, [
                'class' => Article::class,
                'choice_label' => 'libelle',
                'label' => false,
                'placeholder' => 'Sélectionnez un article',
                'attr' => [
                    'data-stock' => json_encode($options['stock_data']),
                    'data-prix' => json_encode($options['prix_data']),
                ],
            ])
            ->add('prixUnitaire', TextType::class, [
                'label' => false,
                'attr' => ['readonly' => true],
            ])
            ->add('quantite', IntegerType::class, [
                'label' => false,
                'attr' => ['min' => 1],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'stock_data' => [],
            'prix_data' => [],
        ]);
    }
}
