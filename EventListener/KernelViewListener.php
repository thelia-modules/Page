<?php

namespace Page\EventListener;

use Page\Model\PageQuery;
use Page\Service\PageService;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use SmartyException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\Template\TemplateHelperInterface;
use TheliaSmarty\Template\SmartyParser;

class KernelViewListener implements EventSubscriberInterface
{
    protected TemplateHelperInterface $templateHelper;
    protected SmartyParser $parser;
    protected RequestStack $requestStack;
    protected PageService $pageService;

    public function __construct(
        RequestStack  $requestStack,
        SmartyParser  $parser,
        TemplateHelperInterface $templateHelper,
        PageService $pageService
    ) {
        $this->requestStack = $requestStack;
        $this->parser = $parser;
        $this->templateHelper = $templateHelper;
        $this->pageService = $pageService;
    }

    /**
     * @throws SmartyException
     * @throws PropelException
     */
    public function onKernelView(ViewEvent $event)
    {
        $currentTemplateDefinition = $this->parser->getTemplateDefinition();
        $currentFallbackToDefaultTemplate = $this->parser->getFallbackToDefaultTemplate();

        $this->parser->setTemplateDefinition($this->templateHelper->getActiveFrontTemplate(), true);

        $request = $this->requestStack->getCurrentRequest();
        $view = $request->attributes->get('_view');
        $viewId = $request->attributes->get($view . '_id');

        if ($view !== 'page' && $view !== "index") {
            return;
        }

        if ($viewId) {
            $page = PageQuery::create()->filterById($viewId)
            ->findOne();
        }

        if ($view === "index") {
            $page = PageQuery::create()
            ->filterByIsHome(1)
            ->usePageTypeQuery('', Criteria::INNER_JOIN)
            ->endUse()
            ->findOne();
        }

        if (!$page) {
            return;
        }

        $view = $this->pageService->getPageTemplateName($page);

        if (null !== $view) {
            $request->attributes->set('_view', $view);
        }

        $page->setLocale($this->requestStack->getCurrentRequest()->getSession()->getLang()->getLocale());
        $this->parser->assign('page', $page);

        if ($currentTemplateDefinition) {
            $this->parser->setTemplateDefinition($currentTemplateDefinition, $currentFallbackToDefaultTemplate);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => [
                ['onKernelView', 3]
            ],
        ];
    }
}
