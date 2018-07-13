<?php
namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class RecoverType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('username', TextType::class, [
                'attr' => ['class' => 'input is-medium', 'placeholder' => 'username. ', 'autocomplete' => 'off'],
            ])
            ->add('password', PasswordType::class, [
                'attr' => ['class' => 'input is-medium', 'placeholder' => 'new password. ', 'autocomplete' => 'off'],
            ])
            ->add('key', TextType::class, [
                'attr' => ['class' => 'input is-medium', 'placeholder' => 'recover key. ', 'autocomplete' => 'off'],
            ])
            ->add('reset', SubmitType::class, [
                'attr' => [
                    'class' => 'button is-success',
                ],
            ]);
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
        ));
    }
}
