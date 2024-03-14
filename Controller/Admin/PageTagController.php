<?php

namespace Page\Controller\Admin;

use Exception;
use Page\Form\PageTagForm;
use Page\Model\PageTag;
use Page\Model\PageTagQuery;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\Exception\FormValidationException;

#[Route('/admin/page-tag', name:'page_tag')]
class PageTagController extends BaseAdminController
{
    #[Route('', name:'_list', methods: 'GET')]
    public function listPageTagAction(): Response|RedirectResponse
    {
        return $this->render('page-tags-list');
    }

    #[Route('/new', name:'_new_tag', methods: 'GET')]
    public function newPageTagViewAction(Request $request): Response|RedirectResponse
    {
        return $this->render('new-tag');
    }

    #[Route('/create', name:'_create_tag_action', methods: 'POST')]
    public function createPageTagAction(ParserContext $parserContext): Response|RedirectResponse
    {
        $form = $this->createForm(PageTagForm::class);

        try {
            $formData = $this->validateForm($form)->getData();

            $pageTag = new PageTag();
            $pageTag->setTag($formData['tag']);
            $pageTag->save();

            return $this->generateSuccessRedirect($form);
        } catch (FormValidationException $e) {
            $error_message = $this->createStandardFormValidationErrorMessage($e);
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }

        $form->setErrorMessage($error_message);
        $parserContext
            ->addForm($form)
            ->setGeneralError($error_message);

        return $this->generateErrorRedirect($form);
    }

    #[Route('/edit/{tagId}', name:'_edit_page', methods: 'GET')]
    public function editPageTagViewAction($tagId): Response|RedirectResponse
    {
        if (null === $tag = PageTagQuery::create()->findOneById($tagId)) {
            return $this->generateRedirect('/admin/page-tags?error=' . "no tags found");
        }

        return $this->render('edit-tag', [
            "page_tag_id" => $tag->getId(),
            "page_tag" => $tag->getTag()
            ]
        );
    }

    #[Route('/update/{tagId}', name:'_update_page', methods: 'POST')]
    public function updatePageTagViewAction(ParserContext $parserContext, $tagId): Response|RedirectResponse
    {
        $form = $this->createForm(PageTagForm::class);

        try {
            $formData = $this->validateForm($form)->getData();

            if (null !== $tag = PageTagQuery::create()->findOneById($tagId)) {
                $tag->setTag($formData['tag']);
                $tag->save();
            }

            return $this->generateSuccessRedirect($form);
        } catch (FormValidationException $e) {
            $error_message = $this->createStandardFormValidationErrorMessage($e);
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }

        $form->setErrorMessage($error_message);
        $parserContext
            ->addForm($form)
            ->setGeneralError($error_message);

        return $this->generateErrorRedirect($form);
    }

    #[Route('/delete/{tagId}', name:'_delete_page')]
    public function deletePageTagAction($tagId): Response|RedirectResponse
    {
        try {
            $tag = PageTagQuery::create()->findOneById($tagId);

            if (!$tag) {
                throw new Exception("Tag not found");
            }

            $tag->delete();

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            return $this->generateRedirect('/admin/page-tag?error=' . $error_message);
        }

        return $this->generateRedirect('/admin/page-tag');
    }
}