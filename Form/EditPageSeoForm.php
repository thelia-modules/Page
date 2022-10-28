<?php

namespace Page\Form;

use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Form\BaseForm;

class EditPageSeoForm extends BaseForm
{
    /**
     * @return null
     */
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                'title',
                TextType::class,
                [
                    'label' => $this->translator->trans('Page title', [], 'page.bo.default'),
                    'constraints' => [
                        new NotBlank(),
                    ]
                ]
            )
            ->add(
                'slug',
                TextType::class,
                [
                    'label' => $this->translator->trans('Rewriting URL', [], 'page.bo.default'),
                    'constraints' => [
                        new NotBlank(),
                    ]
                ]
            )
            ->add(
                'meta_title',
                TextType::class,
                [
                    'required' => false,
                    'label' => $this->translator->trans('Meta title', [], 'page.bo.default'),
                ]
            )
            ->add(
                'meta_description',
                TextareaType::class,
                [
                    'required' => false,
                    'label' => $this->translator->trans('Meta description', [], 'page.bo.default')
                ]
            )
            ->add(
                'meta_keyword',
                TextareaType::class,
                [
                    'required' => false,
                    'label' => $this->translator->trans('Meta keywords', [], 'page.bo.default')
                ]
            );

        return null;
    }
}
