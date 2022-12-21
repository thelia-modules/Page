<?php

namespace Page\Controller\Admin;

use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Log\Tlog;
use Thelia\Model\LangQuery;
use Thelia\Tools\Rest\ResponseRest;
use Thelia\Tools\URL;
use TheliaLibrary\Service\LibraryImageService;
use TheliaLibrary\Service\LibraryItemImageService;

/**
 * Class PageImageController
 *
 * @author Bertrand Tourlonias <btourlonias@openstudio.fr>
 */

/**
 * @Route("/admin/page/image", name="page_image")
 */
class PageImageController extends BaseAdminController
{
    /**
     * @Route("/list/{pageId}", name="_image_list", methods="POST")
     *
     * @param $pageId
     * @return string|Response
     */
    public function getImageListAction($pageId): Response|string
    {
        return $this->render('includes/page-image-list', ["page_id" => $pageId]);
    }


    /**
     * @Route("/upload/{pageId}", name="_image_upload", methods="POST")
     *
     * @param Request $request
     * @param Session $session
     * @param LibraryItemImageService $libraryItemImageService,
     * @param $pageId
     * @return ResponseRest
     */
    public function uploadImageAction(
        Request                 $request,
        Session                 $session,
        LibraryItemImageService $libraryItemImageService,
                                $pageId
    ): ResponseRest
    {
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
     * @Route("/delete/{pageImageId}/{pageId}", name="_image_delete", methods="GET")
     *
     * @param LibraryItemImageService $libraryItemImageService
     * @param LibraryImageService $libraryImageService
     * @param $pageImageId
     * @param $pageId
     * @return RedirectResponse|Response
     */
    public function deleteImageAction(
        LibraryItemImageService $libraryItemImageService,
        LibraryImageService     $libraryImageService,
                                $pageImageId,
                                $pageId
    ): RedirectResponse|Response
    {
        try {
            $libraryItemImageService->deleteImageAssociation($pageImageId);
            $libraryImageService->deleteImage($pageImageId);
        } catch (Exception $e) {
            Tlog::getInstance()->error($e->getMessage());
            //TODO: handle error message
        }

        return new RedirectResponse(URL::getInstance()->absoluteUrl('admin/page/edit/' . $pageId .'?current_tab=images'));
    }
}
