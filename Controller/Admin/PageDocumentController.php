<?php

namespace Page\Controller\Admin;

use Exception;
use Page\Model\PageDocumentQuery;
use Page\Page;
use Page\Service\PageDocumentService;
use Page\Service\PageService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\File\Exception\ProcessFileException;
use Thelia\Core\Security\AccessManager;
use Thelia\Tools\Rest\ResponseRest;
use Thelia\Tools\TokenProvider;
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
    use ChecksAdminWriteAccess;

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
     * @param TokenProvider $tokenProvider
     * @param PageDocumentService $pageDocumentService
     * @param PageService $pageService
     * @param $pageId
     * @return Response
     */
    #[Route('/upload/{pageId}', name: '_upload', methods: ['POST'])]
    public function uploadDocumentAction(
        Request             $request,
        Session             $session,
        TokenProvider       $tokenProvider,
        PageDocumentService $pageDocumentService,
        PageService         $pageService,
        $pageId
    ): Response {
        if (null !== $refusal = $this->refuseUnlessAllowed($request, $tokenProvider, AccessManager::UPDATE)) {
            return $refusal;
        }

        try {
            $extensionBlackListed = [];

            $locale = $session->getAdminEditionLang()->getLocale();
            $fileBeingUploaded = $request->files->get('file');

            // The key written at activation is `extension_black_listed`; the
            // camel-cased one was read here and never matched anything.
            $configured = Page::getConfigValue('extension_black_listed')
                ?: Page::getConfigValue('extensionBlackListed');

            if ($configured) {
                $extensionBlackListed = explode(',', $configured);
            }

            $pageDocumentService->checkFile($fileBeingUploaded, $extensionBlackListed);
            $fileUploaded = $pageDocumentService->uploadedPageDocument($fileBeingUploaded, $pageId, $extensionBlackListed);

            $pageService->savePageDocument($fileUploaded, $pageId, $locale);
        } catch (ProcessFileException $e) {
            return new ResponseRest(['status' => false, 'message' => $e->getMessage()], 'json', 415);
        } catch (Exception $e) {
            // ResponseRest serialises an array; handing it the message as a
            // string turned every refusal into a 500.
            return new ResponseRest(['status' => false, 'message' => $e->getMessage()], 'json', 400);
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
    #[Route('/delete/{pageDocumentId}/{pageId}', name: '_delete', requirements: ['pageDocumentId' => '\d+', 'pageId' => '\d+'], methods: ['POST'])]
    public function deleteDocumentAction(
        Request                 $request,
        TokenProvider           $tokenProvider,
        Session                 $session,
        PageDocumentService     $pageDocumentService,
        LibraryItemImageService $libraryItemImageService,
        LibraryImageService     $libraryImageService,
        $pageDocumentId,
        $pageId
    ): RedirectResponse|Response {
        if (null !== $refusal = $this->refuseUnlessAllowed($request, $tokenProvider, AccessManager::DELETE)) {
            return $refusal;
        }

        if (!PageDocumentQuery::create()->filterById($pageDocumentId)->filterByPageId($pageId)->exists()) {
            return $this->pageNotFound();
        }

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
     * @param TokenProvider $tokenProvider
     * @param PageDocumentService $pageDocumentService
     * @param int $pageId
     * @return Response
     */
    #[Route('/update-position/{pageId}', name: '_update_position', requirements: ['pageId' => '\d+'], methods: ['POST'])]
    public function updatePositionDocumentAction(
        Request             $request,
        TokenProvider       $tokenProvider,
        PageDocumentService $pageDocumentService,
        int                 $pageId
    ): Response {
        if (null !== $refusal = $this->refuseUnlessAllowed($request, $tokenProvider, AccessManager::UPDATE)) {
            return $refusal;
        }

        if (!PageDocumentQuery::create()->filterById((int) $request->request->get('document_id'))->filterByPageId($pageId)->exists()) {
            return new ResponseRest(['status' => false, 'message' => 'Page document not found'], 'json', 404);
        }

        try {
            $pageDocumentService->updatePositionPageDocument(
                $request->request->get('document_id'),
                $request->request->get('position')
            );

            return new ResponseRest(['status' => true, 'message' => '']);
        } catch (Exception $e) {
            return new ResponseRest(['status' => false, 'message' => $e->getMessage()], 'json', 404);
        }
    }
}
