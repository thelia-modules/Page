<?php

namespace Page\Service;

use Page\Model\PageType;
use Page\Model\PageTypeQuery;
use Propel\Runtime\Exception\PropelException;

class PageTypeProvider
{
    /**
     * @param string $type
     * @return void
     * @throws PropelException
     */
    public function createPageType(string $type): void
    {
        $pageType = new PageType();
        $pageType
            ->setType($type)
            ->save();
    }

    /**
     * @param int $pageTypeId
     * @param string $type
     * @return void
     * @throws PropelException
     */
    public function updatePageType(int $pageTypeId, string $type): void
    {
        $pageType = PageTypeQuery::create()->findPk($pageTypeId);

        if (!$pageType) {
            throw new \Exception("Page type not found");
        }

        $pageType
            ->setType($type)
            ->save();
    }

    /**
     * @param int $pageTypeId
     * @return void
     * @throws PropelException
     */
    public function deletePageType(int $pageTypeId): void
    {
        $pageType = PageTypeQuery::create()->findPk($pageTypeId);

        if (!$pageType) {
            throw new \Exception("Page type not found");
        }

        $pageType->delete();
    }
}