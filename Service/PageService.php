<?php

namespace Page\Service;

use InvalidArgumentException;
use Page\Model\Page as PageModel;
use Page\Model\PageDocument;
use Page\Model\PageDocumentQuery;
use Page\Model\PageQuery;
use Page\Page;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Core\Template\Exception\ResourceNotFoundException;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Core\Translation\Translator;

class PageService
{
    protected ParserInterface $parser;

    public function __construct(protected ParserResolver $parserResolver, protected TemplateHelperInterface $templateHelper) {}

    public function getPageTemplateName(PageModel $page)
    {

        if ($page->isHome() && $this->templateExist('page-home')) {
            return 'page-home';
        } 

        if ($page->getCode() && $this->templateExist('page-' . $page->getCode())) {
            return  'page-' . $page->getCode();
        }
        
        if ($page->getPageType() && $this->templateExist('page-' . $page->getPageType()->getType())) {
            return 'page-' . $page->getPageType()->getType();
        }

        $parent = $page->getParent();

        if (null === $parent) {
            return null;
        }

        return $this->getPageTemplateName($parent, true);
    }


    /**
     * @param $pageId
     * @return \Page\Model\Page
     */
    public function getPageData($pageId): \Page\Model\Page
    {
        $page = PageQuery::create()
            ->filterById($pageId)
            ->findOne();

        if (!$page) {
            throw new InvalidArgumentException(Translator::getInstance()->trans('Page not found', [], Page::DOMAIN_NAME));
        }

        return $page;
    }

    /**
     * @param UploadedFile $uploadedFile
     * @param int $pageId
     * @param string $locale
     * @return PageDocument
     * @throws PropelException
     */
    public function savePageDocument(UploadedFile $uploadedFile, int $pageId, string $locale = 'en_US'): PageDocument
    {
        $page = PageQuery::create()->findPk($pageId);

        if (!$page) {
            throw new InvalidArgumentException(Translator::getInstance()->trans('Page not found', [], Page::DOMAIN_NAME));
        }

        $pageDocument = PageDocumentQuery::create()
            ->usePageDocumentI18nQuery()
            ->filterByLocale($locale)
            ->filterByFile($uploadedFile->getFilename())
            ->endUse()
            ->findOne();

        if (!$pageDocument) {
            $pageDocument = new PageDocument();
            $pageDocument->setVisible(1);
        }

        $pageDocument
            ->setPageId($pageId)
            ->setLocale($locale)
            ->setFile($uploadedFile->getFilename())
            ->setTitle($uploadedFile->getFilename())
            ->save();

        return $pageDocument;
    }


    /**
     * @param string $mode
     * @param int $pageId
     * @param int|null $position
     * @return void
     */
    public function changePosition(string $mode, int $pageId, int $position = null): void
    {
        if (null !== $page = PageQuery::create()->findPk($pageId)) {
            switch ($mode) {
                case 'down':
                    $page->moveToNextSiblingOf($page->getNextSibling());
                    break;
                case 'up':
                    $page->moveToPrevSiblingOf($page->getPrevSibling());
                    break;
                default:
                    $page->changeAbsolutePosition($position);
                    break;
            }
        }
    }



    public function templateExist(string $templateName) : bool {
        $path = $this->templateHelper->getActiveFrontTemplate()->getAbsolutePath();

        try {
            $this->parser = $this->parserResolver->getParser($path, $templateName);
        } catch (ResourceNotFoundException) {
            return false;
        }

        $filePath = $path . DS . $templateName . '.' . $this->parser->getFileExtension();

        return file_exists($filePath);
    }
}
