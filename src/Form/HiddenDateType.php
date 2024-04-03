<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

class HiddenDateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addModelTransformer(new CallbackTransformer(
            fn(?\DateTime $arguments) => $arguments?->format('U.u'),
            fn($arguments) => \DateTime::createFromFormat('U.u', $arguments)
        ));
    }

    public function getParent()
    {
        return HiddenType::class;
    }
}
