<?php

namespace Page\Loop;

use Page\Model\PageTypeQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;

class PageTypeLoop extends BaseI18nLoop implements PropelSearchLoopInterface
{
    /**
     * {@inheritdoc}
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('id'),
        );
    }

    
    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $type) {
            $loopResultRow = new LoopResultRow($type);

            $loopResultRow
                ->set('ID', $type->getId())
                ->set('TYPE', $type->getType())
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }


    public function buildModelCriteria()
    {
        $id = $this->getId();
        $search = PageTypeQuery::create();

        if (null !== $id) {
            $search->filterById($id, Criteria::IN);
        }
        return $search;
    }
}
