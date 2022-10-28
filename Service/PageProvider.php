<?php

namespace Page\Service;

use Page\Model\Page;
use Page\Model\PageQuery;
use Propel\Runtime\Exception\PropelException;
use Thelia\Model\RewritingUrl;
use Thelia\Model\RewritingUrlQuery;
use TheliaBlocks\Model\BlockGroup;
use TheliaBlocks\Model\ItemBlockGroup;

class PageProvider
{
    /**
     * @param string $title
     * @param string $slug
     * @param int|null $typeId
     * @param int|null $blockGroupId
     * @return void
     * @throws PropelException
     */
    public function createPage(
        string $title,
        string $slug,
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
                ->setSlug($slug)
                ->save();

            $blockGroupId = $newBlockGroup->getId();
        }

        $page = new Page();

        $page
            ->setLocale($locale)
            ->setSlug($slug)
            ->setTitle($title)
            ->setDescription($description)
            ->setBlockGroupId($blockGroupId)
            ->setTypeId($typeId)
            ->setVisible(true)
            ->save();

        $computedUrl = $this->handleRewritingUrl($page, $slug, $locale);
        $page->setLocale($locale)->setSlug($computedUrl)->save();

        (new ItemBlockGroup())
            ->setBlockGroupId($blockGroupId)
            ->setItemType('page')
            ->setItemId($page->getId())
            ->save();
    }

    /**
     * @param string $title
     * @param string $slug
     * @param int|null $typeId
     * @param int|null $blockGroupId
     * @param string|null $description
     * @param string|null $chapo
     * @param string|null $postscriptum
     * @param string|null $meta_title
     * @param string|null $meta_keyword
     * @param string|null $meta_description
     * @param string $locale
     * @return void
     */
    public function updatePage(
        int    $pageId,
        string $title,
        string $slug,
        int    $typeId = null,
        string $description = null,
        string $chapo = null,
        string $postscriptum = null,
        string $locale = 'en_US'): void
    {
        $page = PageQuery::create()->findPk($pageId);

        if (!$page) {
            throw new \Exception('Page not Found');
        }

        $page->setLocale($locale);
        $page
            ->setTitle($title)
            ->setDescription($description)
            ->setSlug($slug)
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
     * @throws PropelException
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
            throw new \Exception('Page not Found');
        }

        $computedUrl = $this->handleRewritingUrl($page, $slug, $locale);

        $page->setLocale($locale);
        $page
            ->setTitle($title)
            ->setSlug($computedUrl)
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
            throw new \Exception('Page not Found');
        }

        $page
            ->setLocale($locale)
            ->setMetaTitle($metaTitle)
            ->setMetaDescription($metaDescription)
            ->setMetaKeywords($metaKeyWord)
            ->save();
    }

    /**
     * @param Page $page
     * @param $slug
     */
    public function handleRewritingUrl(Page $page, $slug, $locale = 'en_US', $redirectUrl = null): string|null
    {
        $redirect = null;

        if ($redirectUrl) {
            $redirect = RewritingUrlQuery::create()
                ->filterByUrl($redirectUrl)
                ->findOne();

            if ($redirect?->getUrl() === $slug) {
                return $slug;
            }
        }

        $rule = new RewritingUrl();

        $rule
            ->setViewLocale($locale)
            ->setViewId($page->getId())
            ->setView('page')
            ->setUrl($slug)
            ->save();

        if ($redirectUrl && $redirect) {
            $redirect
                ->setRedirected($rule->getId())
                ->save();
        }

        return $rule->getUrl();
    }
}
