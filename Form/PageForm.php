<?php

namespace Page\Form;

use OpenApi\Constraint\NotBlank;
use Page\Model\PageType;
use Page\Model\PageTypeQuery;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Form\BaseForm;
use TheliaBlocks\Model\BlockGroup;
use TheliaBlocks\Model\BlockGroupQuery;

class PageForm extends BaseForm
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
                'code',
                TextType::class,
                [
                    'required' => false,
                    'label' => $this->translator->trans('Page code', [], 'page.bo.default')
                ]
            )
            ->add(
                'parent',
                HiddenType::class
            )
            ->add(
                'description',
                TextareaType::class,
                [
                    'required' => false,
                    'label' => $this->translator->trans('Description', [], 'page.bo.default')
                ]
            )
            ->add(
                'type',
                ChoiceType::class,
                [
                    "choices" => $this->getPageTypes(),
                    'required' => false,
                    'label' => $this->translator->trans('Page type', [], 'page.bo.default'),
                    'placeholder' => $this->translator->trans('Choose a page type', [], 'page.bo.default')
                ]
            )
            ->add(
                'thelia-block',
                ChoiceType::class,
                [
                    "choices" => $this->getTheliaBlocs(),
                    'required' => false,
                    'label' => $this->translator->trans('Thelia block', [], 'page.bo.default'),
                    'placeholder' => $this->translator->trans('Choose a block', [], 'page.bo.default')
                ]
            );

        return null;
    }

    /**
     * @return array
     */
    protected function getPageTypes(): array
    {
        $choices = [];
        $types = PageTypeQuery::create()->find();
        $choices['No type'] = '';
        /** @var PageType $type */
        foreach ($types as $type) {
            $choices[$type->getType()] = $type->getId();
        }

        return $choices;
    }

    /**
     * @return array
     */
    protected function getTheliaBlocs(): array
    {
        $locale = $this->getRequest()->getSession()->getAdminEditionLang()->getLocale();
        $choices = [];

        $blocks = BlockGroupQuery::create()
            ->filterByVisible(1)
            ->find();

        $choices[] = null;

        /** @var BlockGroup $block */
        foreach ($blocks as $block) {
            $block->setLocale($locale);
            $choices[$block->getTitle()] = $block->getId();
        }

        return $choices;
    }
}
