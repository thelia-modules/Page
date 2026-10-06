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
use Thelia\Core\File\SvgSanitizer;
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
        'php', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phps', 'pht', 'phtm', 'phtml', 'phpt', 'phar',
        'shtml', 'shtm', 'stm',
        'asp', 'aspx', 'cgi', 'pl', 'py', 'sh', 'bash', 'jsp', 'jspx',
        'htaccess', 'htpasswd',
    ];

    /**
     * Extensions a browser opens as a page or runs as a script when the
     * document is served from the shop. Refused whatever the module is
     * configured with.
     *
     * An SVG drawing is kept once stripped of its active content, see
     * storeSvgDocument().
     */
    public const BROWSER_ACTIVE_EXTENSIONS = [
        'html', 'htm', 'xhtml', 'xhtm', 'xht', 'mht', 'mhtml',
        'xml', 'xsl', 'xslt', 'rdf', 'mml', 'atom', 'rss', 'xspf', 'kml', 'xul', 'svgz',
        'js', 'mjs', 'cjs',
    ];

    private const SVG_EXTENSION = 'svg';

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

        $this->refuseExtensionsOf($uploadedFile->getClientOriginalName(), $extensionBlackListed);
    }

    /**
     * @param UploadedFile $uploadedFile
     * @param int $pageId
     * @param array $extensionBlackListed
     * @return UploadedFile
     */
    public function uploadedPageDocument(UploadedFile $uploadedFile, int $pageId, array $extensionBlackListed = []): UploadedFile
    {
        $fileName = $this->storedFileName($uploadedFile, $pageId);

        // The name written to the disk is not the name that was sent: "x.php ."
        // is stored as "x.php.<page id>". It is the stored name a web server
        // reads, so it is checked again.
        $this->refuseExtensionsOf($fileName, $extensionBlackListed);

        $fileSystem = new Filesystem();

        $directory = Page::getDocumentsUploadDir();

        if (!$fileSystem->exists($directory)) {
            $fileSystem->mkdir($directory);
        }

        if (!file_exists($directory . DS . $fileName)) {
            if (self::SVG_EXTENSION === strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) {
                $this->storeSvgDocument($uploadedFile, $directory . DS . $fileName);
            } else {
                $fileSystem->rename($uploadedFile->getPathname(), $directory . DS . $fileName);
            }
        }

        return new UploadedFile($directory . DS . $fileName, $fileName);
    }

    private function storedFileName(UploadedFile $uploadedFile, int $pageId): string
    {
        $fileName = $uploadedFile->getClientOriginalName();

        if (!empty($extension = $uploadedFile->getClientOriginalExtension())) {
            $extension = '.' . strtolower($extension);
            $fileName = str_replace($extension, '', $fileName);
        }

        return strtolower(preg_replace('/[^a-zA-Z0-9-_\.]/', '', $fileName . $pageId . $extension));
    }

    /**
     * A web server can be configured on any dotted part of a name, and Apache
     * reads every one of them: "report.php.pdf" or "page.html.5" are refused
     * like "report.php" and "page.html". An SVG drawing is only accepted under
     * its final extension, where it is stored stripped of its active content.
     */
    private function refuseExtensionsOf(string $fileName, array $extensionBlackListed): void
    {
        $refused = array_unique(
            array_merge(
                self::ALWAYS_REFUSED_EXTENSIONS,
                self::BROWSER_ACTIVE_EXTENSIONS,
                array_filter(array_map(static fn ($extension) => strtolower(trim((string) $extension)), $extensionBlackListed))
            )
        );

        $parts = array_map(static fn (string $part): string => strtolower(trim($part)), array_slice(explode('.', $fileName), 1));

        $found = array_values(array_intersect($parts, $refused));

        if ([] === $found && \in_array(self::SVG_EXTENSION, array_slice($parts, 0, -1), true)) {
            $found = [self::SVG_EXTENSION];
        }

        if ([] !== $found) {
            $message = Translator::getInstance()
                ->trans(
                    'Files with the following extension are not allowed: %extension, please do an archive of the file if you want to upload it',
                    [
                        '%extension' => $found[0],
                    ]
                );
            throw new ProcessFileException($message, 403);
        }
    }

    /**
     * An SVG drawing served from the shop runs its scripts when it is opened
     * on its own: it is stored once the core sanitizer has removed them, and
     * refused on a core that has no sanitizer or when it is not a well-formed
     * SVG document.
     */
    private function storeSvgDocument(UploadedFile $uploadedFile, string $destination): void
    {
        $content = file_get_contents($uploadedFile->getPathname());

        $sanitized = class_exists(SvgSanitizer::class) && false !== $content
            ? (new SvgSanitizer())->sanitize($content)
            : null;

        if (null === $sanitized) {
            $message = Translator::getInstance()
                ->trans(
                    'Files with the following extension are not allowed: %extension, please do an archive of the file if you want to upload it',
                    [
                        '%extension' => self::SVG_EXTENSION,
                    ]
                );
            throw new ProcessFileException($message, 403);
        }

        $fileSystem = new Filesystem();
        $fileSystem->dumpFile($destination, $sanitized);
        $fileSystem->remove($uploadedFile->getPathname());
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
            (new LibraryImageDetacher($libraryItemImageService, $libraryImageService))->detach($theliaLibraryImage);
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
