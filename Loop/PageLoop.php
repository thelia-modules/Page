<?php

namespace Page\Loop;

use Page\Model\Map\PageTableMap;
use Page\Model\PageQuery;
use Page\Model\Page as PageModel;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;
use TheliaBlocks\Model\Map\BlockGroupI18nTableMap;
use TheliaBlocks\Model\Map\BlockGroupTableMap;
use TheliaBlocks\Model\Map\ItemBlockGroupTableMap;

class PageLoop extends BaseI18nLoop implements PropelSearchLoopInterface
{
    /**
     * {@inheritdoc}
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument('id'),
            Argument::createIntListTypeArgument('exclude_id'),
            Argument::createAlphaNumStringListTypeArgument('tag'),
            Argument::createAlphaNumStringListTypeArgument('exclude_tag'),
            Argument::createBooleanOrBothTypeArgument('visible', 1),
            Argument::createIntTypeArgument('parent_tree_left'),
            Argument::createIntTypeArgument('parent_tree_right'),
            Argument::createIntTypeArgument('parent_tree_level', 0),
            Argument::createBooleanTypeArgument('only_direct_child'),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(['alpha', 'alpha-reverse', 'id', 'position', 'position-reverse'])
                ),
                'position'
            ),
        );
    }


    public function parseResults(LoopResult $loopResult)
    {
        $locale = $this->getCurrentRequest()->getSession()->getLang()->getLocale();
        /** @var PageModel $page */
        foreach ($loopResult->getResultDataCollection() as $page) {
            $loopResultRow = new LoopResultRow($page);

            $loopResultRow
                ->set('ID', $page->getId())
                ->set('PAGE_TYPE', $page->getPageType())
                ->set('PAGE_CODE', $page->getCode())
                ->set('PAGE_URL', $page->getRewrittenUrl($locale))
                ->set('PAGE_TAG', $page->getTag())
                ->set('PAGE_VISIBLE', $page->getVisible())
                ->set('PAGE_TREE_LEFT', $page->getTreeLeft())
                ->set('PAGE_TREE_RIGHT', $page->getTreeRight())
                ->set('PAGE_TREE_LEVEL', $page->getTreeLevel())
                ->set('PAGE_BLOCK_GROUP_ID', $page->hasVirtualColumn('block_group_id') ? $page->getVirtualColumn('block_group_id') : null)
                ->set('PAGE_TITLE', $page->getVirtualColumn('i18n_TITLE'))
                ->set('PAGE_CHAPO', $page->getVirtualColumn('i18n_CHAPO'))
                ->set('PAGE_DESCRIPTION', $page->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('PAGE_POSTSCRIPTUM', $page->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set('PAGE_META_TITLE', $page->getVirtualColumn('i18n_META_TITLE'))
                ->set('PAGE_META_DESCRIPTION', $page->getVirtualColumn('i18n_META_DESCRIPTION'))
                ->set('PAGE_META_KEYWORDS', $page->getVirtualColumn('i18n_META_KEYWORDS'))
                ->set('PAGE_BLOCK_GROUP_TITLE', $page->hasVirtualColumn('block_group_title') ? $page->getVirtualColumn('block_group_title') : null);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }


    public function buildModelCriteria()
    {
        $visible = $this->getVisible();

        $search = PageQuery::create();

        $this->configureI18nProcessing($search, ['TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM', 'META_TITLE', 'META_DESCRIPTION', 'META_KEYWORDS']);

        if (null !== $id = $this->getId()) {
            $search->filterById($id, Criteria::IN);
        }

        if (null !== $excludeId = $this->getExcludeId()) {
            $search->filterById($excludeId, Criteria::NOT_IN);
        }

        if (null !== $tag = $this->getTag()) {
            $search->filterByTag($tag);
        }

        if (null !== $exludeTag = $this->getExcludeTag()) {
            $search->filterByTag($exludeTag, Criteria::NOT_IN);
        }

        if ($visible !== BooleanOrBothType::ANY) {
            $search->filterByVisible($visible ? 1 : 0);
        }

        if (null !== $this->getParentTreeLeft()) {
            $search->filterByTreeLeft($this->getParentTreeLeft(), Criteria::GREATER_THAN);
            $search->filterByTreeRight($this->getParentTreeRight(), Criteria::LESS_THAN);
        }

        if (true === $this->getOnlyDirectChild()) {
            $search->filterByTreeLevel($this->getParentTreeLevel() + 1);
        }

        $joinItemBlockGroup = new Join();
        $joinItemBlockGroup->setJoinType(Criteria::LEFT_JOIN);
        $joinItemBlockGroup->addExplicitCondition(
            PageTableMap::TABLE_NAME,
            'ID',
            null,
            ItemBlockGroupTableMap::TABLE_NAME,
            'ITEM_ID',
            null
        );
        $search->addJoinObject($joinItemBlockGroup, 'item_block_group')
            ->addJoinCondition(
                'item_block_group',
                '`item_block_group`.`item_type` = ?',
                'page',
                null,
                \PDO::PARAM_STR
            );
        $search->withColumn('item_block_group.block_group_id', 'block_group_id');

        $locale = $this->getCurrentRequest()->getSession()->getLang()->getLocale();
        $joinBlockGroupI18n = new Join();
        $joinBlockGroupI18n->setJoinType(Criteria::LEFT_JOIN);
        $joinBlockGroupI18n->addExplicitCondition(
            ItemBlockGroupTableMap::TABLE_NAME,
            'BLOCK_GROUP_ID',
            null,
            BlockGroupI18nTableMap::TABLE_NAME,
            'ID',
            null
        );

        $search->addJoinObject($joinBlockGroupI18n, 'block_group_i18n')
            ->addJoinCondition(
                'block_group_i18n',
                '`block_group_i18n`.`locale` = ?',
                $locale,
                null,
                \PDO::PARAM_STR
            );
        $search->withColumn('block_group_i18n.title', 'block_group_title');

        $orders = $this->getOrder();

        foreach ($orders as $order) {
            switch ($order) {
                case 'id':
                    $search->orderById();
                    break;
                case 'created':
                    $search->orderByCreatedAt();
                    break;
                case 'created_reverse':
                    $search->orderByCreatedAt(Criteria::DESC);
                    break;
                case 'alpha':
                    $search->addAscendingOrderByColumn('i18n_TITLE');
                    break;
                case 'alpha-reverse':
                    $search->addDescendingOrderByColumn('i18n_TITLE');
                    break;
                case 'position':
                    $search->orderByTreeLeft();
                    break;
                case 'position-reverse':
                    $search->orderByTreeLeft(Criteria::DESC);
                    break;
            }
        }

        return $search;
    }
}
