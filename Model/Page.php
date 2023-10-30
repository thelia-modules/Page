<?php

namespace Page\Model;

use Page\Model\Base\Page as BasePage;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Thelia\Model\Tools\UrlRewritingTrait;

/**
 * Skeleton subclass for representing a row from the 'page' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class Page extends BasePage
{
    use UrlRewritingTrait;

    public function getRewrittenUrlViewName()
    {
        return 'page';
    }

    protected function createSlug()
    {
        // create the slug based on the `slug_pattern` and the object properties
        $slug = $this->code ?? $this->createRawSlug();
        // truncate the slug to accommodate the size of the slug column
        $slug = $this->limitSlugSize($slug);
        // add an incremental index to make sure the slug is unique
        $slug = $this->makeSlugUnique($slug);

        return $slug;
    }

    public function postDelete(ConnectionInterface $con = null): void
    {
        parent::postDelete($con);

        $this->markRewrittenUrlObsolete();
    }

    public function getParent(ConnectionInterface $con = null)
    {
        return $this->setSameLocale(parent::getParent($con));
    }

    public function getPrevSibling(ConnectionInterface $con = null)
    {
        return $this->setSameLocale(parent::getPrevSibling($con));
    }

    public function getNextSibling(ConnectionInterface $con = null)
    {
        return $this->setSameLocale(parent::getNextSibling($con));
    }

    public function getChildren(Criteria $criteria = null, ConnectionInterface $con = null)
    {
        return $this->setSameLocaleCollectionOrArray(parent::getChildren($criteria, $con));
    }

    public function getFirstChild(Criteria $criteria = null, ConnectionInterface $con = null)
    {
        return $this->setSameLocale(parent::getFirstChild($con));
    }

    public function getLastChild(Criteria $criteria = null, ConnectionInterface $con = null)
    {
        return $this->setSameLocale(parent::getLastChild($con));
    }

    public function getSiblings($includeNode = false, Criteria $criteria = null, ConnectionInterface $con = null)
    {
        return $this->setSameLocaleCollectionOrArray(parent::getSiblings($criteria, $con));
    }

    public function getDescendants(Criteria $criteria = null, ConnectionInterface $con = null)
    {
        return $this->setSameLocaleCollectionOrArray(parent::getDescendants($criteria, $con));
    }

    public function getBranch(Criteria $criteria = null, ConnectionInterface $con = null)
    {
        return $this->setSameLocaleCollectionOrArray(parent::getBranch($criteria, $con));
    }

    public function getAncestors(Criteria $criteria = null, ConnectionInterface $con = null)
    {
        return $this->setSameLocaleCollectionOrArray(parent::getAncestors($criteria, $con));
    }

    private function setSameLocale(?Page $page = null)
    {
        if (null !== $page) {
            $page->setLocale($this->getLocale());
        }

        return $page;
    }

    private function setSameLocaleCollectionOrArray($pages)
    {
        if (!is_array($pages)) {
            $pages = iterator_to_array($pages);
        }

        return array_map(
            function (Page $page) {
                return $page->setLocale($this->getLocale());
            },
            $pages
        );
    }
}
