<?php

namespace Page\Loop;

use Page\Model\PageQuery;
use Page\Model\Page as PageModel;
use Propel\Runtime\ActiveQuery\Criteria;
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
            Argument::createAlphaNumStringListTypeArgument('slug'),
            Argument::createAlphaNumStringListTypeArgument('exclude_slug'),
            Argument::createAlphaNumStringListTypeArgument('tag'),
            Argument::createAlphaNumStringListTypeArgument('exclude_tag'),
            Argument::createBooleanOrBothTypeArgument('visible', 1),
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
        /** @var PageModel $page */
        foreach ($loopResult->getResultDataCollection() as $page) {
            $loopResultRow = new LoopResultRow($page);

            if (null !== $blockGroup = $page->getBlockGroup()){
                $blockGroup->setLocale($this->getCurrentRequest()->getSession()->getLang()->getLocale());
            }

            $loopResultRow
                ->set('ID', $page->getId())
                ->set('PAGE_TYPE', $page->getPageType())
                ->set('PAGE_SLUG', $page->getVirtualColumn('i18n_SLUG'))
                ->set('PAGE_TAG', $page->getTag())
                ->set('PAGE_VISIBLE', $page->getVisible())
                ->set('PAGE_POSITION', $page->getPosition())
                ->set('PAGE_BLOCK_GROUP_ID', $blockGroup?->getId())
                ->set('PAGE_TITLE', $page->getVirtualColumn('i18n_TITLE'))
                ->set('PAGE_CHAPO', $page->getVirtualColumn('i18n_CHAPO'))
                ->set('PAGE_DESCRIPTION', $page->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('PAGE_POSTSCRIPTUM', $page->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set('PAGE_META_TITLE', $page->getVirtualColumn('i18n_META_TITLE'))
                ->set('PAGE_META_DESCRIPTION', $page->getVirtualColumn('i18n_META_DESCRIPTION'))
                ->set('PAGE_META_KEYWORDS', $page->getVirtualColumn('i18n_META_KEYWORDS'))
                ->set('PAGE_BLOCK_GROUP_TITLE', $blockGroup?->getTitle());

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }


    public function buildModelCriteria()
    {
        $visible = $this->getVisible();

        $search = PageQuery::create();

        $currentLocale = $this->getCurrentRequest()->getSession()->getLang()->getLocale();

        $this->configureI18nProcessing($search, ['SLUG', 'TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM', 'META_TITLE', 'META_DESCRIPTION', 'META_KEYWORDS']);

        if (null !== $id = $this->getId()) {
            $search->filterById($id, Criteria::IN);
        }

        if (null !== $excludeId = $this->getExcludeId()) {
            $search->filterById($excludeId, Criteria::NOT_IN);
        }

        if (null !== $slug = $this->getSlug()) {
            $search
                ->usePageI18nQuery()
                    ->filterByLocale($currentLocale)
                    ->filterBySlug($slug, Criteria::IN)
                ->endUse();
        }

        if (null !== $slugs = $this->getExcludeSlug()) {
            $search
                ->usePageI18nQuery()
                    ->filterByLocale($currentLocale)
                    ->filterBySlug($slugs, Criteria::NOT_IN)
                ->endUse();
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
                    $search->orderByPosition();
                    break;
                case 'position-reverse':
                    $search->orderByPosition(Criteria::DESC);
                    break;
            }
        }

        return $search;
    }
}
