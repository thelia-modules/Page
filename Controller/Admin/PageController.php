<?php

namespace Page\Controller\Admin;

use Page\Form\EditPageForm;
use Page\Form\EditPageSeoForm;
use Page\Service\PageProvider;
use Page\Service\PageService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Page\Form\PageForm;
use Page\Model\PageQuery;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\LangQuery;

/**
 * Class PageController
 *
 * @author Damien Foulhoux <dfoulhoux@openstudio.fr>
 * @author Bertrand Tourlonias <btourlonias@openstudio.fr>
 */

/**
 * @Route("/admin/page", name="page")
 */
class PageController extends BaseAdminController
{
    /**
     * @Route("", name="_list", methods="GET")
     */
    public function listPageAction()
    {
        return $this->render('pages-list');
    }

    /**
     * @Route("/new", name="_new_page", methods="GET")
     */
    public function newPageViewAction()
    {
        return $this->render('new-page');
    }

    /**
     * @Route("/create", name="_create_page_action", methods="POST")
     *
     * @param PageProvider $pageProvider
     * @param ParserContext $parserContext
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response|null
     *
     */
    public function createPageAction(Session $session, PageProvider $pageProvider, ParserContext $parserContext)
    {
        $form = $this->createForm(PageForm::class);
        $locale = $session->getAdminEditionLang()->getLocale();

        try {
            $formData = $this->validateForm($form)->getData();

            $pageProvider->createPage(
                $formData['title'],
                $formData['slug'],
                $formData['type'],
                $formData['thelia-block'],
                $formData['description'],
                $locale
            );

            return $this->generateSuccessRedirect($form);
        } catch (FormValidationException $e) {
            $error_message = $this->createStandardFormValidationErrorMessage($e);
        } catch (\Exception $e) {
            $error_message = $e->getMessage();
        }

        $form->setErrorMessage($error_message);
        $parserContext
            ->addForm($form)
            ->setGeneralError($error_message);

        return $this->generateErrorRedirect($form);
    }

    /**
     * @Route("/edit/{pageId}", name="_edit_page", methods="GET")
     *
     * @param Session $session
     * @param PageService $pageService
     * @param $pageId
     * @return string|RedirectResponse|\Symfony\Component\HttpFoundation\Response|\Thelia\Core\HttpFoundation\Response
     */
    public function editPageViewAction(Request $request, Session $session, PageService $pageService, $pageId)
    {
        try {
            $locale = $session->getAdminEditionLang()->getLocale();

            if ($editlanguageId = $request->get('edit_language_id')) {
                $lang = LangQuery::create()->findPk($editlanguageId);
                $locale = ($lang) ? $lang->getLocale() : $locale;
            }

            $page = $pageService->getPageData($pageId);
            $page->setLocale($locale);

        } catch (\Exception $e) {
            return $this->generateRedirect('/admin/page?error=' . $e->getMessage());
        }

        return $this->render('edit-page', [
            "page_id" => $pageId,
            "page_slug" => $page->getSlug(),
            "page_title" => $page->getTitle(),
            "page_description" => $page->getDescription(),
            "page_chapo" => $page->getChapo(),
            "page_postscriptum" => $page->getPostscriptum(),
            "page_meta_title" => $page->getMetaTitle(),
            "page_meta_description" => $page->getMetaDescription(),
            "page_meta_keywords" => $page->getMetaKeywords()
        ]);
    }

    /**
     * @Route("/update/{pageId}", name="_update_page_action", methods="POST")
     */
    public function updatePageAction(Request $request, Session $session, PageProvider $pageProvider, ParserContext $parserContext, int $pageId)
    {
        $form = $this->createForm(EditPageForm::class);

        $locale = $session->getAdminEditionLang()->getLocale();

        if ($editlanguageId = $request->get('edit_language_id')) {
            $lang = LangQuery::create()->findPk($editlanguageId);
            $locale = ($lang) ? $lang->getLocale() : $locale;
        }

        try {
            $formData = $this->validateForm($form)->getData();

            $pageProvider->updatePage(
                $pageId,
                $formData['title'],
                $formData['slug'],
                $formData['type'],
                $formData['description'],
                $formData['chapo'],
                $formData['postscriptum'],
                $locale
            );

            return $this->generateSuccessRedirect($form);
        } catch (FormValidationException $e) {
            $error_message = $this->createStandardFormValidationErrorMessage($e);
        } catch (\Exception $e) {
            $error_message = $e->getMessage();
        }

        $form->setErrorMessage($error_message);
        $parserContext
            ->addForm($form)
            ->setGeneralError($error_message);

        return $this->generateErrorRedirect($form);
    }

    /**
     * @Route("/update/{pageId}/seo", name="_update_seo_page_action", methods="POST")
     */
    public function updateSeoPageAction(Request $request, Session $session, PageProvider $pageProvider, ParserContext $parserContext, int $pageId)
    {
        $form = $this->createForm(EditPageSeoForm::class);

        $locale = $session->getAdminEditionLang()->getLocale();

        if ($editlanguageId = $request->get('edit_language_id')) {
            $lang = LangQuery::create()->findPk($editlanguageId);
            $locale = ($lang) ? $lang->getLocale() : $locale;
        }

        try {
            $formData = $this->validateForm($form)->getData();

            $pageProvider->updateSeoPage(
                $pageId,
                $formData['title'],
                $formData['slug'],
                $formData['meta_title'],
                $formData['meta_description'],
                $formData['meta_keyword'],
                $locale
            );

            return $this->generateSuccessRedirect($form);
        } catch (FormValidationException $e) {
            $error_message = $this->createStandardFormValidationErrorMessage($e);
        } catch (\Exception $e) {
            $error_message = $e->getMessage();
        }

        $form->setErrorMessage($error_message);
        $parserContext
            ->addForm($form)
            ->setGeneralError($error_message);

        return $this->generateErrorRedirect($form);
    }

    /**
     * @Route("/delete/{pageId}", name="_delete_page_action", methods="GET")
     * @param $pageId
     * @return RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function deletePageAction($pageId)
    {
        try {
            $page = PageQuery::create()
                ->filterById($pageId)
                ->findOne();

            if (!$page) {
                throw new \Exception("Page not found");
            }

            $page->delete();

        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            return $this->generateRedirect('/admin/page?error=' . $error_message);
        }

        return $this->generateRedirect('/admin/page');
    }
}
