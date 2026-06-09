<?php

namespace Page\Form;

use Symfony\Component\Validator\Constraints\NotBlank;
use Page\Page;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Form\BaseForm;

class PageTagForm extends BaseForm
{
    protected function buildForm(): void
    {
        $this->formBuilder
            ->add(
                'tag',
                TextType::class,
                [
                    'label' => $this->translator->trans('Tag', [], Page::DOMAIN_NAME),
                    'constraints' => [
                        new NotBlank(),
                    ]
                ]
            )
        ;
    }
}