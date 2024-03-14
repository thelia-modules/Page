<?php

namespace Page\Controller\Admin;

use Exception;
use Page\Form\EditPageForm;
use Page\Form\EditPageSeoForm;
use Page\Model\Page;
use Page\Service\PageProvider;
use Page\Service\PageService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Page\Form\PageForm;
use Page\Model\PageQuery;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Model\LangQuery;
use Thelia\Tools\URL;

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
    public function newPageViewAction(Request $request)
    {
        return $this->render('new-page', ['parent' => $request->get('parent')]);
    }

    /**
     * @Route("/create", name="_create_page_action", methods="POST")
     *
     * @param Session $session
     * @param PageProvider $pageProvider
     * @param ParserContext $parserContext
     * @return RedirectResponse|Response|null
     */
    public function createPageAction(Session $session, PageProvider $pageProvider, ParserContext $parserContext)
    {
        $form = $this->createForm(PageForm::class);
        $locale = $session->getAdminEditionLang()->getLocale();
        
        try {
            $formData = $this->validateForm($form)->getData();
            
            $pageProvider->createPage(
                $formData['title'],
                $formData['code'],
                $formData['type'] ?: null,
                $formData['thelia-block'],
                $formData['description'],
                $locale,
                $formData['parent']
            );

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

    /**
     * @Route("/edit/{pageId}", name="_edit_page", methods="GET")
     *
     * @param Request $request
     * @param Session $session
     * @param PageService $pageService
     * @param $pageId
     * @return string|RedirectResponse|Response|\Thelia\Core\HttpFoundation\Response
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

            $ancestors = $page->getAncestors();
            if (!is_array($ancestors)) {
                $ancestors = iterator_to_array($ancestors);
            }

            $ancestors = array_filter(array_map(
                function (Page $page) use ($locale) {
                    $page->setLocale($locale);
                    if ($page->isRoot()) {
                        return null;
                    }
                    return [
                        'id' => $page->getId(),
                        'title' => $page->getTitle(),
                    ];
                },
                $ancestors
            ));

        } catch (Exception $e) {
            return $this->generateRedirect('/admin/page?error=' . $e->getMessage());
        }

        return $this->render('edit-page', [
            "page_id" => $pageId,
            "page_url" => $page->getRewrittenUrl($locale),
            "page_title" => $page->getTitle(),
            "page_code" => $page->getCode(),
            "page_tag" => $page->getTag(),
            "page_tree_left" => $page->getTreeLeft(),
            "page_tree_right" => $page->getTreeRight(),
            "page_tree_level" => $page->getTreeLevel(),
            "page_type_id" => $page->getTypeId(),
            "page_description" => $page->getDescription(),
            "page_chapo" => $page->getChapo(),
            "page_postscriptum" => $page->getPostscriptum(),
            "page_meta_title" => $page->getMetaTitle(),
            "page_meta_description" => $page->getMetaDescription(),
            "page_meta_keywords" => $page->getMetaKeywords(),
            "ancestors" => $ancestors,
            'current_tab' => $request->get('current_tab')
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
                $formData['code'],
                $formData['tag'],
                $formData['type'] ?: null,
                $formData['description'],
                $formData['chapo'],
                $formData['postscriptum'],
                $locale
            );

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
                $formData['url'],
                $formData['meta_title'],
                $formData['meta_description'],
                $formData['meta_keyword'],
                $locale
            );

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

    /**
     * @Route("/update-position", name="_update_page_position_action", methods="GET")
     */
    public function updatePagePosition(
        Request     $request,
        PageService $pageService
    ) {
        try {
            $mode = $request->get('mode');
            $pageId = $request->get('page_id');

            if (!$mode || !$pageId) {
                throw new Exception('Page or positon not set');
            }

            $position = $request->get('position');

            $pageService->changePosition($mode, $pageId, $position);

        } catch (Exception $ex) {
            return $this->generateRedirect(URL::getInstance()->absoluteUrl(
                '/admin/page',
                [
                    "error" => $ex->getMessage()
                ]
            ));
        }

        return $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/page'));
    }

    /**
     * @Route("/set-visible", name="_toggle_page_visibility_action", methods="GET")
     */
    public function togglePageVisibility(
        Request     $request
    ) {
        try {
            $pageId = $request->get('page_id');
            $visible = $request->get('visible');

            if (!$pageId) {
                throw new Exception("Page not found");
            }

            $page = PageQuery::create()
                ->filterById($pageId)
                ->findOne();

            if (!$page) {
                throw new Exception("Page not found");
            }

            $page->setVisible($visible)->save();

        } catch (Exception $ex) {
            return $this->generateRedirect(URL::getInstance()->absoluteUrl(
                '/admin/page',
                [
                    "error" => $ex->getMessage()
                ]
            ));
        }

        return $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/page'));
    }

    /**
     * @Route("/set-home", name="_toggle_page_home_action", methods="GET")
     */
    public function toggleHome(
        Request     $request
    ) {
        try {
            $pageId = $request->get('page_id');
            

            if (!$pageId) {
                throw new Exception("Page not found");
            }

            $prevHomepage = PageQuery::create()
                ->filterByIsHome(1)
                ->findOne();

            
            $page = PageQuery::create()
                ->filterById($pageId)
                ->findOne();

            if (!$page) {
                throw new Exception("Page not found");
            }

            if(null !== $prevHomepage) {
                $prevHomepage->setIsHome(0)->save();
            }

            $page->setIsHome(1);
            $page->save();

        } catch (Exception $ex) {
            return $this->generateRedirect(URL::getInstance()->absoluteUrl(
                '/admin/page',
                [
                    "error" => $ex->getMessage()
                ]
            ));
        }

        return $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/page'));
    }

    /**
     * @Route("/delete/{pageId}", name="_delete_page_action", methods="GET")
     * @param $pageId
     * @return RedirectResponse|Response
     */
    public function deletePageAction($pageId)
    {
        try {
            $page = PageQuery::create()
                ->filterById($pageId)
                ->findOne();

            if (!$page) {
                throw new Exception("Page not found");
            }

            $page->delete();

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            return $this->generateRedirect('/admin/page?error=' . $error_message);
        }

        return $this->generateRedirect('/admin/page');
    }
}
