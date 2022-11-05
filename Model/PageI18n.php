<?php

namespace Page\Model;

use Page\Model\Base\PageI18n as BasePageI18n;
use Propel\Runtime\Connection\ConnectionInterface;

/**
 * Skeleton subclass for representing a row from the 'page_i18n' table.
 *
 *
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 */
class PageI18n extends BasePageI18n
{
    /**
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function postInsert(ConnectionInterface $con = null): void
    {
        parent::postInsert($con);

        $page= $this->getPage();
        $page->generateRewrittenUrl($this->getLocale(), $con);
    }
}
