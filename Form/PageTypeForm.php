<?php

namespace Page\Form;

use OpenApi\Constraint\NotBlank;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Form\BaseForm;

class PageTypeForm extends BaseForm
{
    /**
     * @return null
     */
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                'type',
                TextType::class,
                [
                    'label' => $this->translator->trans('Type', [], 'pages.bo.default'),
                    "required" => true,
                    'constraints' => [
                        new NotBlank(),
                    ]
                ]
            )
        ;

        return null;
    }
}
