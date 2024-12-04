<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Page\EventListener;

use Page\Model\Map\PageTableMap;
use Page\Model\Page;
use Page\Model\PageQuery;
use Page\Service\PageService;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Model\Tools\ModelCriteriaTools;
use TheliaBlocks\Model\Map\BlockGroupI18nTableMap;
use TheliaBlocks\Model\Map\ItemBlockGroupTableMap;
use TheliaBlocks\Service\JsonBlockService;

class KernelViewListener implements EventSubscriberInterface
{
    protected ParserInterface $parser;

    public static ?Page $page = null;

    public function __construct(
        protected RequestStack $requestStack,
        protected ParserResolver $parserResolver,
        protected TemplateHelperInterface $templateHelper,
        protected PageService $pageService,
        protected JsonBlockService $jsonBlockService,
    ) {
    }

    /**
     * @throws \SmartyException
     * @throws PropelException
     */
    public function onKernelView(ViewEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $view = $request->attributes->get('_view');
        $viewId = $request->attributes->get($view.'_id');

        if ($view !== 'page' && $view !== 'index') {
            return;
        }

        $pageQuery = PageQuery::create();

        ModelCriteriaTools::getI18n(
            false,
            $this->requestStack->getCurrentRequest()?->getSession()?->getLang()?->getId(),
            $pageQuery,
            $this->requestStack->getCurrentRequest()?->getSession()?->getLang()?->getLocale(),
            ['TITLE', 'CHAPO', 'DESCRIPTION', 'POSTSCRIPTUM', 'META_TITLE', 'META_DESCRIPTION', 'META_KEYWORDS'],
            null,
            'ID',
            true
        );

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
        $pageQuery->addJoinObject($joinItemBlockGroup, 'item_block_group')
            ->addJoinCondition(
                'item_block_group',
                '`item_block_group`.`item_type` = ?',
                'page',
                null,
                \PDO::PARAM_STR
            );
        $pageQuery->withColumn('item_block_group.block_group_id', 'block_group_id');

        $locale = $this->requestStack->getCurrentRequest()->getSession()->getLang()->getLocale();
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

        $pageQuery->addJoinObject($joinBlockGroupI18n, 'block_group_i18n')
            ->addJoinCondition(
                'block_group_i18n',
                '`block_group_i18n`.`locale` = ?',
                $locale,
                null,
                \PDO::PARAM_STR
            );
        $pageQuery->withColumn('block_group_i18n.title', 'block_group_title');
        $pageQuery->withColumn('block_group_i18n.json_content', 'block_group_content');

        if ($viewId) {
            $page = $pageQuery->filterById($viewId)
            ->findOne();
        }

        if ($view === 'index') {
            $page = $pageQuery
            ->filterByIsHome(1)
            ->findOne();
        }

        if (!$page) {
            return;
        }

        self::$page = $page;

        $path = $this->templateHelper->getActiveFrontTemplate()->getAbsolutePath();
        $this->parser = $this->parserResolver->getParser($path, $view);
        // Define the template that should be used
        $this->parser->setTemplateDefinition(
            $this->parser->getTemplateDefinition() ?: $this->templateHelper->getActiveFrontTemplate()
        );

        $currentTemplateDefinition = $this->parser->getTemplateDefinition();
        $currentFallbackToDefaultTemplate = $this->parser->getFallbackToDefaultTemplate();

        $view = $this->pageService->getPageTemplateName($page);

        if (null !== $view) {
            $request->attributes->set('_view', $view);
        }

        $page->setLocale($this->requestStack->getCurrentRequest()?->getSession()?->getLang()?->getLocale());

        if ($currentTemplateDefinition) {
            $this->parser->setTemplateDefinition($currentTemplateDefinition, $currentFallbackToDefaultTemplate);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => [
                ['onKernelView', 3],
            ],
        ];
    }
}
