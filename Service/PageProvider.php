<?php

namespace Page\Service;

use Exception;
use Page\Model\Page;
use Page\Model\PageQuery;
use Page\Model\PageTag;
use Page\Model\PageTagCombination;
use Page\Model\PageTagCombinationQuery;
use Page\Model\PageTagQuery;
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
        string $code = null,
        int    $typeId = null,
        int    $blockGroupId = null,
        string $description = null,
        string $locale = 'en_US',
        int $parentId = null
    ): void {
        
        if (!$blockGroupId) {
            $newBlockGroup = new BlockGroup();
            $newBlockGroup
                ->setLocale($locale)
                ->setTitle($title)
                ->setSlug($title)
                ->save();

            $blockGroupId = $newBlockGroup->getId();
        }
        
        $parent = null;
        if (null !== $parentId) {
            $parent = PageQuery::create()->filterById($parentId)->findOne();
        }

        if (null === $parent) {
            $parent = PageQuery::create()->findRoot();
        }

        if (null === $parent) {
            $root = new Page();
            $root->makeRoot();
            $root->save();
            $parent = $root;
        }

        $page = new Page();
        
        $page
            ->setLocale($locale)
            ->setTitle($title)
            ->setCode($code)
            ->setDescription($description)
            ->setTypeId($typeId)
            ->setVisible(true)
            ->insertAsLastChildOf($parent)
            ->save();

        $page->setLocale($locale)
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
     * @param ?string $tag
     * @param int|null $typeId
     * @param string|null $description
     * @param string|null $chapo
     * @param string|null $postscriptum
     * @param string $locale
     * @return void
     * @throws Exception
     */
    public function updatePage(
        int    $pageId,
        string $title,
        string $code = null,
        array $tags = null,
        ?int    $typeId = null,
        string $description = null,
        string $chapo = null,
        string $postscriptum = null,
        string $locale = 'en_US'
    ): void {
        $page = PageQuery::create()->findPk($pageId);

        if (!$page) {
            throw new Exception('Page not Found');
        }

        foreach ($tags as $tag) {
            $this->createOrUpdatePageTagCombinaison($pageId, str_replace(" ", "", $tag));
        }

        $page->setLocale($locale);
        $page
            ->setTitle($title)
            ->setCode($code)
            ->setDescription($description)
            ->setTypeId($typeId)
            ->setChapo($chapo)
            ->setPostscriptum($postscriptum)
            ->save();
    }

    /**
     * @param int $pageId
     * @param string $title
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
        string $url,
        string $meta_title = null,
        string $meta_description = null,
        string $meta_keyword = null,
        string $locale = 'en_US'
    ): void {
        $page = PageQuery::create()->findPk($pageId);

        if (!$page) {
            throw new Exception('Page not Found');
        }

        $page->setRewrittenUrl($locale, $url);

        $page
            ->setLocale($locale)
            ->setTitle($title)
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
        string $locale = 'en_US'
    ): void {
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

    /**
     * @throws PropelException
     */
    public function createOrUpdatePageTagCombinaison($pageId, $tag): PageTagCombination
    {
        if (null === $pageTag = PageTagQuery::create()->findOneByTag($tag)) {
            $pageTag = new PageTag();
            $pageTag->setTag($tag);
            $pageTag->save();
        }

        if (null === $pageTagCombination = PageTagCombinationQuery::create()->filterByPageId($pageId)->findOneByPageTagId($pageTag->getId())) {
            $pageTagCombination = new PageTagCombination();
        }

        $pageTagCombination
            ->setPageId($pageId)
            ->setPageTagId($pageTag->getId())
            ->save();

        return $pageTagCombination;
    }
}
