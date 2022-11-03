<?php

namespace Page\Controller\Admin;

use Page\Form\PageForm;
use Page\Form\PageTypeForm;
use Page\Service\PageTypeProvider;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Template\ParserContext;
use Thelia\Form\Exception\FormValidationException;

/**
 * Class PageController
 *
 * @author Damien Foulhoux <dfoulhoux@openstudio.fr>
 * @author Bertrand Tourlonias <btourlonias@openstudio.fr>
 */

/**
 * @Route("/admin/page-type", name="page_type")
 */
class PageTypeController extends BaseAdminController
{
    /**
     * @Route("/", name="_page_type_list", methods="GET")
     */
    public function listPageTypeAction()
    {
        return $this->render('page-type.html');
    }

    /**
     * @Route("/create", name="_create_page_type_list", methods="POST")
     */
    public function createPageTypeAction(ParserContext $parserContext, PageTypeProvider $pageTypeProvider)
    {
        $form = $this->createForm(PageTypeForm::class);

        try {
            $formData = $this->validateForm($form)->getData();

            $pageTypeProvider->createPageType(
                $formData['type']
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
     * @Route("/update", name="_update_page_type_list", methods="POST")
     *
     * @param ParserContext $parserContext
     * @param PageTypeProvider $pageTypeProvider
     * @param int $pagesTypeId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response|null
     */
    public function updatePagesTypeAction(ParserContext $parserContext, PageTypeProvider $pageTypeProvider, int $pagesTypeId)
    {
        $form = $this->createForm(PageForm::class);

        try {
            $formData = $this->validateForm($form)->getData();

            $pageTypeProvider->updatePageType(
                $pagesTypeId,
                $formData['type']
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
     * @Route("/delete", name="_update_page_type_list", methods="GET")
     *
     * @param PageTypeProvider $pageTypeProvider
     * @param $pagesTypeId
     * @return string|null
     */
    public function deletePagesTypeAction(PageTypeProvider $pageTypeProvider, $pagesTypeId)
    {
        try {
            $pageTypeProvider->deletePageType($pagesTypeId);

        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            return $this->generateRedirect('admin/page?error=' . $error_message);
        }

        return $this->generateRedirect('admin/page-type');
    }
}
