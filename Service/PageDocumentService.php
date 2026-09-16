<?php

namespace Page\Service;

use Exception;
use Page\Model\PageDocumentQuery;
use Page\Page;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Core\Translation\Translator;
use Thelia\Core\File\Exception\ProcessFileException;
use TheliaLibrary\Model\LibraryItemImageQuery;
use TheliaLibrary\Service\LibraryImageService;
use TheliaLibrary\Service\LibraryItemImageService;
use function ini_get;

class PageDocumentService
{
    /**
     * Extensions a web server may decide to run rather than hand over.
     *
     * Refused whatever the module is configured with: the
     * `extension_black_listed` configuration only adds to this list.
     */
    public const ALWAYS_REFUSED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phps', 'pht', 'phtml', 'phar',
        'asp', 'aspx', 'cgi', 'pl', 'py', 'sh', 'bash', 'jsp', 'jspx',
        'htaccess', 'htpasswd',
    ];

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

        $refused = array_unique(
            array_merge(
                self::ALWAYS_REFUSED_EXTENSIONS,
                array_filter(array_map(static fn ($extension) => strtolower(trim((string) $extension)), $extensionBlackListed))
            )
        );

        // A name carries all of its dotted parts to the file system, and a
        // server can be configured on any of them: "report.php.pdf" has to be
        // read the same way as "report.php".
        $parts = array_map('strtolower', array_slice(explode('.', $uploadedFile->getClientOriginalName()), 1));

        $found = array_intersect($parts, $refused);

        if ([] !== $found) {
            $message = Translator::getInstance()
                ->trans(
                    'Files with the following extension are not allowed: %extension, please do an archive of the file if you want to upload it',
                    [
                        '%extension' => reset($found),
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
