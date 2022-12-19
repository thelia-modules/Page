<?php

namespace Page\Controller\Admin;

use Exception;
use Page\Model\PageImageQuery;
use Page\Page;
use Page\Service\PageImageService;
use Page\Service\PageService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Tools\Rest\ResponseRest;
use Thelia\Tools\URL;

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
     * @param PageImageService $pageImageService
     * @param PageService $pageService
     * @param $pageId
     * @return ResponseRest
     */
    public function uploadImageAction(
        Request             $request,
        Session             $session,
        PageImageService    $pageImageService,
        PageService         $pageService,
                            $pageId
    ): ResponseRest
    {
        try {
            $extensionBlackListed = [];

            $locale = $session->getAdminLang()->getLocale();
            $fileBeingUploaded = $request->files->get('file');

            if (Page::getConfigValue('extensionBlackListed')) {
                $extensionBlackListed = explode(',', Page::getConfigValue('extensionBlackListed'));
            }

            $pageImageService->checkFile($fileBeingUploaded, $extensionBlackListed);
            $fileUploaded = $pageImageService->uploadedPageImage($fileBeingUploaded, $pageId);

            $pageService->savePageImage($fileUploaded, $pageId, $locale);

        } catch (Exception $e) {
            return new ResponseRest($e->getMessage(), 'text', 404);
        }

        return new ResponseRest(['status' => true, 'message' => '']);
    }

    /**
     * @Route("/delete/{pageImageId}/{pageId}", name="_image_delete", methods="GET")
     *
     * @param Session $session
     * @param PageImageService $pageImageService
     * @param $pageImageId
     * @param $pageId
     * @return RedirectResponse|Response
     */
    public function deleteImageAction(
        Session             $session,
        PageImageService    $pageImageService,
                            $pageImageId,
                            $pageId
    ): RedirectResponse|Response
    {
        try {
            $locale = $session->getAdminEditionLang()->getLocale();

            $pageImageService->deletePageImage($pageImageId, $locale);

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            //TODO: handle error message
        }

        return new RedirectResponse(URL::getInstance()->absoluteUrl('admin/page/edit/' . $pageId .'?current_tab=images'));
    }
}
