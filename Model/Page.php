<?php

namespace Page\Model;

use Page\Model\Base\Page as BasePage;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Exception\PropelException;
use Thelia\Core\Event\GenerateRewrittenUrlEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Exception\UrlRewritingException;
use Thelia\Model\FolderQuery;
use Thelia\Model\RewritingUrl;
use Thelia\Model\Tools\PositionManagementTrait;
use Thelia\Model\Tools\UrlRewritingTrait;
use Thelia\Tools\URL;

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
    use PositionManagementTrait;
    use UrlRewritingTrait;

    public function getRewrittenUrlViewName()
    {
        return 'page';
    }

    /**
     * {@inheritDoc}
     */
    public function preInsert(ConnectionInterface $con = null)
    {
        $this->setPosition($this->getNextPosition());

        parent::preInsert($con);

        return true;
    }

    public function preDelete(ConnectionInterface $con = null)
    {
        parent::preDelete($con);

        $this->reorderBeforeDelete(
            [
                'id' => $this->getId(),
            ]
        );

        return true;
    }

    public function postDelete(ConnectionInterface $con = null): void
    {
        parent::postDelete($con);

        $this->markRewrittenUrlObsolete();
    }
}
