<?php

namespace Page\Service;

use InvalidArgumentException;
use Page\Model\PageDocument;
use Page\Model\PageDocumentQuery;
use Page\Model\PageQuery;
use Page\Page;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Core\Translation\Translator;
use TheliaBlocks\Model\Map\BlockGroupI18nTableMap;
use TheliaBlocks\Model\Map\BlockGroupTableMap;

class PageService
{
    /**
     * @param $pageId
     * @return \Page\Model\Page
     */
    public function getPageData($pageId): \Page\Model\Page
    {
        $page = PageQuery::create()
            ->filterById($pageId)
            ->useBlockGroupQuery()
                ->withColumn(BlockGroupTableMap::COL_ID, 'block_group_id')
                ->useBlockGroupI18nQuery()
                    ->withColumn(BlockGroupI18nTableMap::COL_TITLE, 'block_group_title')
                ->endUse()
            ->endUse()
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
}
