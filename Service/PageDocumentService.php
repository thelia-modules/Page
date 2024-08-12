<?php

namespace Page\Service;

use Exception;
use Page\Model\PageDocumentQuery;
use Page\Page;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Core\Translation\Translator;
use Thelia\Files\Exception\ProcessFileException;
use TheliaLibrary\Model\LibraryItemImageQuery;
use TheliaLibrary\Service\LibraryImageService;
use TheliaLibrary\Service\LibraryItemImageService;
use function ini_get;

class PageDocumentService
{
    /**
     * @param UploadedFile $uploadedFile
     * @param array $extensionBlackListed
     * @return void
     */
    public function checkFile(UploadedFile $uploadedFile, array $extensionBlackListed = []): void
    {
        if ($uploadedFile->getError() == 1) {
            $sizeError = Translator::getInstance()
                ->trans(
                    'File is too large, please retry with a file having a size less than %size%.',
                    ['%size%' => ini_get('upload_max_filesize')],
                    'core'
                );

            throw new ProcessFileException($sizeError, 403);
        }

        if (empty($extensionBlackListed)) {
            return;
        }

        $regex = "#^(.+)\.(" . implode('|', $extensionBlackListed) . ')$#i';

        if (preg_match($regex, $uploadedFile->getClientOriginalName())) {
            $message = Translator::getInstance()
                ->trans(
                    'Files with the following extension are not allowed: %extension, please do an archive of the file if you want to upload it',
                    [
                        '%extension' => $uploadedFile->getClientOriginalExtension(),
                    ]
                );
            throw new ProcessFileException($message, 403);
        }
    }

    /**
     * @param UploadedFile $uploadedFile
     * @param int $pageId
     * @return UploadedFile
     */
    public function uploadedPageDocument(UploadedFile $uploadedFile, int $pageId): UploadedFile
    {
        $fileSystem = new Filesystem();

        $directory = Page::getDocumentsUploadDir();

        if (!$fileSystem->exists($directory)) {
            $fileSystem->mkdir($directory);
        }

        $fileName = $uploadedFile->getClientOriginalName();

        if (!empty($extension = $uploadedFile->getClientOriginalExtension())) {
            $extension = '.' . strtolower($extension);
            $fileName = str_replace($extension, '', $fileName);
        }

        $fileName = strtolower(preg_replace('/[^a-zA-Z0-9-_\.]/', '', $fileName));
        $fileName .= $pageId . $extension;

        if (!file_exists($directory . DS . $fileName)) {
            $fileSystem->rename($uploadedFile->getPathname(), $directory . DS . $fileName);
        }

        return new UploadedFile($directory . DS . $fileName, $fileName);
    }

    /**
     * @param LibraryItemImageService $libraryItemImageService
     * @param LibraryImageService $libraryImageService
     * @param int $pageDocumentId
     * @param string $locale
     * @return void
     * @throws PropelException
     */
    public function deletePageDocument(
        LibraryItemImageService $libraryItemImageService,
        LibraryImageService     $libraryImageService,
        int $pageDocumentId,
        string $locale
    ): void {
        $pageDocument = PageDocumentQuery::create()
            ->filterById($pageDocumentId)
            ->findOne();

        if (!$pageDocument) {
            throw new Exception("Page not found");
        }

        $directory = Page::getDocumentsUploadDir();
        $fileName = $pageDocument->setLocale($locale)->getFile();

        if (file_exists($filePath = $directory . DS . $fileName)) {
            unlink($filePath);
        }

        $imageDirectory = Page::getImagesUploadDir();
        if (file_exists($filePath = $imageDirectory . DS . $fileName . '.jpg')) {
            unlink($filePath);
        }

        if (null !== $theliaLibraryImage = LibraryItemImageQuery::create()->filterByItemType(Page::PAGE_DOCUMENT_PREVIEW)->findOneByItemId($pageDocument->getId())) {
            $libraryItemImageService->deleteImageAssociation($theliaLibraryImage->getId());
            $libraryImageService->deleteImage($theliaLibraryImage->getImageId());
        }

        $pageDocument->delete();
    }

    /**
     * @param int $pageDocumentId
     * @param int $position
     * @return void
     */
    public function updatePositionPageDocument(int $pageDocumentId, int $position): void
    {
        $pageDocument = PageDocumentQuery::create()
            ->filterById($pageDocumentId)
            ->findOne();

        if (!$pageDocument) {
            throw new Exception("Page document not found");
        }

        $pageDocument->changeAbsolutePosition($position);
    }
}
