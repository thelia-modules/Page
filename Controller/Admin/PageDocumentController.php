<?php

namespace Page\Controller\Admin;

use Exception;
use Page\Page;
use Page\Service\PageDocumentService;
use Page\Service\PageService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Tools\Rest\ResponseRest;
use Thelia\Tools\URL;

/**
 * Class PageDocumentController
 *
 * @author Bertrand Tourlonias <btourlonias@openstudio.fr>
 */

/**
 * @Route("/admin/page/document", name="page_document")
 */
class PageDocumentController extends BaseAdminController
{
    /**
     * @Route("/list/{pageId}", name="_document_list", methods="POST")
     *
     * @param $pageId
     * @return string|Response
     */
    public function getDocumentListAction($pageId): Response|string
    {
        return $this->render('includes/page-document-list', ["page_id" => $pageId]);
    }

    /**
     * @Route("/upload/{pageId}", name="_document_upload", methods="POST")
     *
     * @param Request $request
     * @param Session $session
     * @param PageDocumentService $pageDocumentService
     * @param PageService $pageService
     * @param $pageId
     * @return ResponseRest
     */
    public function uploadDocumentAction(
        Request             $request,
        Session             $session,
        PageDocumentService $pageDocumentService,
        PageService         $pageService,
                            $pageId
    ): ResponseRest
    {
        try {
            $extensionBlackListed = [];

            $locale = $session->getAdminEditionLang()->getLocale();
            $fileBeingUploaded = $request->files->get('file');

            if (Page::getConfigValue('extensionBlackListed')) {
                $extensionBlackListed = explode(',', Page::getConfigValue('extensionBlackListed'));
            }

            $pageDocumentService->checkFile($fileBeingUploaded, $extensionBlackListed);
            $fileUploaded = $pageDocumentService->uploadedPageDocument($fileBeingUploaded, $pageId);

            $pageService->savePageDocument($fileUploaded, $pageId, $locale);

        } catch (Exception $e) {
            return new ResponseRest($e->getMessage(), 'text', 404);
        }

        return new ResponseRest(['status' => true, 'message' => '']);
    }

    /**
     * @Route("/delete/{pageDocumentId}/{pageId}", name="_document_delete", methods="GET")
     *
     * @param Session $session
     * @param PageDocumentService $pageDocumentService
     * @param $pageDocumentId
     * @param $pageId
     * @return RedirectResponse|Response
     */
    public function deleteDocumentAction(
        Session             $session,
        PageDocumentService $pageDocumentService,
                            $pageDocumentId,
                            $pageId
    ): RedirectResponse|Response
    {
        try {
            $locale = $session->getAdminEditionLang()->getLocale();

            $pageDocumentService->deletePageDocument($pageDocumentId, $locale);

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            //TODO: handle error message
        }

        return new RedirectResponse(URL::getInstance()->absoluteUrl('admin/page/edit/' . $pageId .'?current_tab=documents'));
    }
}
