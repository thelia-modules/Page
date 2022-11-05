<?php

namespace Page\Service;

use Exception;
use Page\Model\Page;
use Page\Model\PageQuery;
use Propel\Runtime\Exception\PropelException;
use Thelia\Exception\UrlRewritingException;
use TheliaBlocks\Model\BlockGroup;
use TheliaBlocks\Model\ItemBlockGroup;

class PageProvider
{
    /**
     * @param string $title
     * @param int|null $typeId
     * @param int|null $blockGroupId
     * @param string|null $description
     * @param string $locale
     * @return void
     * @throws PropelException
     */
    public function createPage(
        string $title,
        int    $typeId = null,
        int    $blockGroupId = null,
        string $description = null,
        string $locale = 'en_US'
    ): void
    {
        if (!$blockGroupId) {
            $newBlockGroup = new BlockGroup();
            $newBlockGroup
                ->setLocale($locale)
                ->setTitle($title)
                ->setSlug($title)
                ->save();

            $blockGroupId = $newBlockGroup->getId();
        }

        $page = new Page();

        $page
            ->setLocale($locale)
            ->setTitle($title)
            ->setDescription($description)
            ->setBlockGroupId($blockGroupId)
            ->setTypeId($typeId)
            ->setVisible(true)
            ->save();

        //$computedUrl = $this->handleRewritingUrl($page, $slug, $locale);
        $computedUrl = $page->getRewrittenUrl($locale);

        $page->setLocale($locale)
            ->setSlug($computedUrl)
            ->save();

        (new ItemBlockGroup())
            ->setBlockGroupId($blockGroupId)
            ->setItemType('page')
            ->setItemId($page->getId())
            ->save();
    }

    /**
     * @param int $pageId
     * @param string $title
     * @param int|null $typeId
     * @param string|null $description
     * @param string|null $chapo
     * @param string|null $postscriptum
     * @param string $locale
     * @return void
     * @throws PropelException
     * @throws Exception
     */
    public function updatePage(
        int    $pageId,
        string $title,
        int    $typeId = null,
        string $description = null,
        string $chapo = null,
        string $postscriptum = null,
        string $locale = 'en_US'): void
    {
        $page = PageQuery::create()->findPk($pageId);

        if (!$page) {
            throw new Exception('Page not Found');
        }

        $page->setLocale($locale);
        $page
            ->setTitle($title)
            ->setDescription($description)
            ->setTypeId($typeId)
            ->setChapo($chapo)
            ->setPostscriptum($postscriptum)
            ->save();
    }

    /**
     * @param int $pageId
     * @param string $title
     * @param string $slug
     * @param string|null $meta_title
     * @param string|null $meta_description
     * @param string|null $meta_keyword
     * @param string $locale
     * @return void
     * @throws PropelException|UrlRewritingException
     * @throws Exception
     */
    public function updateSeoPage(
        int    $pageId,
        string $title,
        string $slug,
        string $meta_title = null,
        string $meta_description = null,
        string $meta_keyword = null,
        string $locale = 'en_US'): void
    {
        $page = PageQuery::create()->findPk($pageId);

        if (!$page) {
            throw new Exception('Page not Found');
        }

        $page->setRewrittenUrl($locale, $slug);

        $page
            ->setLocale($locale)
            ->setTitle($title)
            ->setSlug($page->getRewrittenUrl($locale))
            ->setMetaTitle($meta_title)
            ->setMetaDescription($meta_description)
            ->setMetaKeywords($meta_keyword)
            ->save();
    }

    /**
     * @param int $pageId
     * @param string|null $metaTitle
     * @param string|null $metaDescription
     * @param string|null $metaKeyWord
     * @param string $locale
     * @return void
     * @throws PropelException
     * @throws Exception
     */
    public function updateSeo(
        int    $pageId,
        string $metaTitle = null,
        string $metaDescription = null,
        string $metaKeyWord = null,
        string $locale = 'en_US'): void
    {
        $page = PageQuery::create()->findPk($pageId);

        if (!$page) {
            throw new Exception('Page not Found');
        }

        $page
            ->setLocale($locale)
            ->setMetaTitle($metaTitle)
            ->setMetaDescription($metaDescription)
            ->setMetaKeywords($metaKeyWord)
            ->save();
    }
}
