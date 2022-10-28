<?php

namespace Page\Service;

use Page\Model\Map\PageI18nTableMap;
use Page\Model\PageQuery;
use Page\Page;
use Thelia\Core\Translation\Translator;
use TheliaBlocks\Model\Map\BlockGroupI18nTableMap;
use TheliaBlocks\Model\Map\BlockGroupTableMap;

class PageService
{
    /**
     * @param $pageId
     * @return \Page\Model\Page
     */
    public function getPageData($pageId) : \Page\Model\Page
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
            throw new \InvalidArgumentException(Translator::getInstance()->trans('Page not found', [], Page::DOMAIN_NAME));
        }

        return $page;
    }
}