<?php

namespace Page\Loop;

use Page\Model\PageTag;
use Page\Model\PageTagQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;

/**
 * @method getId()
 */
class PageTagLoop extends BaseI18nLoop implements PropelSearchLoopInterface
{
    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(Argument::createIntListTypeArgument('id'),);
    }

    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var PageTag $pageTag */
        foreach ($loopResult->getResultDataCollection() as $pageTag) {
            $loopResultRow = new LoopResultRow($pageTag);

            $loopResultRow
                ->set('ID', $pageTag->getId())
                ->set('TAG', $pageTag->getTag())
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }


    public function buildModelCriteria(): ModelCriteria
    {
        $search = PageTagQuery::create();

        if (null !== $id = $this->getId()) {
            $search->filterById($id, Criteria::IN);
        }

        return $search;
    }
}
