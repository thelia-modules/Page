<?php

namespace Page\Controller\Admin;

use Exception;
use Page\Page;
use Page\Service\PageDocumentService;
use Page\Service\PageService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Tools\Rest\ResponseRest;
use Thelia\Tools\URL;
use TheliaLibrary\Service\LibraryImageService;
use TheliaLibrary\Service\LibraryItemImageService;

/**
 * Class PageDocumentController
 *
 * @author Bertrand Tourlonias <btourlonias@openstudio.fr>
 */

/**
 */
#[Route('/admin/page/document', name: 'page_document')]
class PageDocumentController extends BaseAdminController
{
    #[Route('/list/{pageId}', name: '_list', methods: ['POST'])]
    public function getDocumentListAction(
        Session $session,
        \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher,
        $pageId
    ): Response|string {
        $locale = $session->getLang()->getLocale();

        $documents = [];
        $search = \Page\Model\PageDocumentQuery::create()->filterByPageId($pageId)->orderByPosition();

        foreach ($search->find() as $pageDocument) {
            $pageDocument->setLocale($locale);
            $file = $pageDocument->getFile();

            $documentEvent = new \Thelia\Core\Event\Document\DocumentEvent();
            $documentEvent->setSourceFilepath(sprintf('%s/%s', Page::getDocumentsUploadDir(), $file));
            $documentEvent->setCacheSubdirectory(Page::PAGE_DOCUMENT);
            $dispatcher->dispatch($documentEvent, \Thelia\Core\Event\TheliaEvents::DOCUMENT_PROCESS);

            $documents[] = [
                'id' => $pageDocument->getId(),
                'title' => $pageDocument->getTitle(),
                'visible' => (bool) $pageDocument->getVisible(),
                'position' => $pageDocument->getPosition(),
                'path' => $documentEvent->getDocumentPath(),
            ];
        }

        return $this->render('includes/page-document-list', [
            'page_id' => $pageId,
            'documents' => $documents,
        ]);
    }

    /**
     *
     * @param Request $request
     * @param Session $session
     * @param PageDocumentService $pageDocumentService
     * @param PageService $pageService
     * @param $pageId
     * @return ResponseRest
     */
    #[Route('/upload/{pageId}', name: '_upload', methods: ['POST'])]
    public function uploadDocumentAction(
        Request             $request,
        Session             $session,
        PageDocumentService $pageDocumentService,
        PageService         $pageService,
        $pageId
    ): ResponseRest {
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
     *
     * @param Session $session
     * @param PageDocumentService $pageDocumentService
     * @param $pageDocumentId
     * @param $pageId
     * @return RedirectResponse|Response
     */
    #[Route('/delete/{pageDocumentId}/{pageId}', name: '_delete', methods: ['GET'])]
    public function deleteDocumentAction(
        Session                 $session,
        PageDocumentService     $pageDocumentService,
        LibraryItemImageService $libraryItemImageService,
        LibraryImageService     $libraryImageService,
        $pageDocumentId,
        $pageId
    ): RedirectResponse|Response {
        try {
            $locale = $session->getAdminEditionLang()->getLocale();

            $pageDocumentService->deletePageDocument($libraryItemImageService, $libraryImageService, $pageDocumentId, $locale);
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            //TODO: handle error message
        }

        return new RedirectResponse(URL::getInstance()->absoluteUrl('admin/page/edit/' . $pageId . '?current_tab=documents'));
    }

    /**
     *
     * @param Request $request
     * @param PageDocumentService $pageDocumentService
     * @return void
     */
    #[Route('/update-position/{pageId}', name: '_update_position', methods: ['POST'])]
    public function updatePositionDocumentAction(
        Request             $request,
        PageDocumentService $pageDocumentService
    ) {
        try {
            $pageDocumentService->updatePositionPageDocument(
                $request->request->get('document_id'),
                $request->request->get('position')
            );

            return new ResponseRest(['status' => true, 'message' => '']);
        } catch (Exception $e) {
            return new ResponseRest($e->getMessage(), 'text', 404);
        }
    }
}
