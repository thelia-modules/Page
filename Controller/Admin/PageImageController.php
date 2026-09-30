<?php

namespace Page\Controller\Admin;

use Exception;
use Page\Service\LibraryImageDetacher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Security\AccessManager;
use Thelia\Log\Tlog;
use Thelia\Model\LangQuery;
use Thelia\Tools\Rest\ResponseRest;
use Thelia\Tools\TokenProvider;
use Thelia\Tools\URL;
use TheliaLibrary\Model\LibraryItemImageQuery;
use TheliaLibrary\Service\LibraryImageService;
use TheliaLibrary\Service\LibraryItemImageService;

/**
 * Class PageImageController
 *
 * @author Bertrand Tourlonias <btourlonias@openstudio.fr>
 */

/**
 */
#[Route('/admin/page/image', name: 'page_image')]
class PageImageController extends BaseAdminController
{
    use ChecksAdminWriteAccess;

    #[Route('/list/{pageId}', name: '_list', methods: ['POST'])]
    public function getImageListAction(LibraryImageService $libraryImageService, $pageId): Response|string
    {
        $images = [];
        $itemImages = \TheliaLibrary\Model\LibraryItemImageQuery::create()
            ->filterByItemType('page')
            ->filterByItemId($pageId)
            ->orderByPosition()
            ->find();

        foreach ($itemImages as $itemImage) {
            $libraryImage = $itemImage->getLibraryImage();
            if (null === $libraryImage) {
                continue;
            }
            $images[] = [
                'id' => $itemImage->getId(),
                'title' => $libraryImage->getTitle(),
                'url' => $libraryImageService->getImagePublicUrl($libraryImage, 200, 100),
            ];
        }

        return $this->render('includes/page-image-list', [
            'page_id' => $pageId,
            'images' => $images,
        ]);
    }


    /**
     *
     * @param Request $request
     * @param Session $session
     * @param TokenProvider $tokenProvider
     * @param LibraryItemImageService $libraryItemImageService,
     * @param $pageId
     * @return Response
     */
    #[Route('/upload/{pageId}', name: '_upload', methods: ['POST'])]
    public function uploadImageAction(
        Request                 $request,
        Session                 $session,
        TokenProvider           $tokenProvider,
        LibraryItemImageService $libraryItemImageService,
                                $pageId
    ): Response
    {
        if (null !== $refusal = $this->refuseUnlessAllowed($request, $tokenProvider, AccessManager::UPDATE)) {
            return $refusal;
        }

        try {
            $locale = $session->getAdminLang()->getLocale();
            $fileBeingUploaded = $request->files->get('file');

            $fileName = $fileBeingUploaded->getClientOriginalName();

            $itemImage = $libraryItemImageService->createAndAssociateImage(
                $fileBeingUploaded,
                $fileName,
                $locale,
                'page',
                $pageId,
                null,
                1
            );
            $libraryImage = $itemImage->getLibraryImage();
            $libraryFilePath = $libraryImage->getFileName();

            $langs = LangQuery::create()->find();

            foreach ($langs as $lang) {
                $libraryImage->setLocale($lang->getLocale())
                    ->setTitle($fileName)
                    ->setFileName($libraryFilePath)
                    ->save();
            }

        } catch (Exception $e) {
            return new ResponseRest($e->getMessage(), 'text', 404);
        }

        return new ResponseRest(['status' => true, 'message' => '']);
    }

    /**
     *
     * @param Request $request
     * @param TokenProvider $tokenProvider
     * @param LibraryImageDetacher $libraryImageDetacher
     * @param int $pageImageId
     * @param int $pageId
     * @return RedirectResponse|Response
     */
    #[Route('/delete/{pageImageId}/{pageId}', name: '_delete', requirements: ['pageImageId' => '\d+', 'pageId' => '\d+'], methods: ['POST'])]
    public function deleteImageAction(
        Request              $request,
        TokenProvider        $tokenProvider,
        LibraryImageDetacher $libraryImageDetacher,
        int                  $pageImageId,
        int                  $pageId
    ): RedirectResponse|Response
    {
        if (null !== $refusal = $this->refuseUnlessAllowed($request, $tokenProvider, AccessManager::DELETE)) {
            return $refusal;
        }

        // The id is the one of the page association, never of the library image:
        // the library is shared with the rest of the shop.
        $itemImage = LibraryItemImageQuery::create()
            ->filterById($pageImageId)
            ->filterByItemType('page')
            ->filterByItemId($pageId)
            ->findOne();

        if (null === $itemImage) {
            return $this->pageNotFound();
        }

        try {
            $libraryImageDetacher->detach($itemImage);
        } catch (Exception $e) {
            Tlog::getInstance()->error($e->getMessage());
            //TODO: handle error message
        }

        return new RedirectResponse(URL::getInstance()->absoluteUrl('admin/page/edit/' . $pageId .'?current_tab=images'));
    }
}
