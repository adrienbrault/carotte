<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use function Psl\Json\decode;
use function Psl\Json\encode;

class HiddenJsonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer(new CallbackTransformer(
            fn($arguments) => encode($arguments),
            fn($arguments) => decode($arguments, true)
        ));
    }

    public function getParent()
    {
        return HiddenType::class;
    }
}