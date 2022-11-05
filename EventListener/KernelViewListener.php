<?php

namespace Page\EventListener;

use Page\Model\PageQuery;
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
    public function __construct(
        protected RequestStack            $requestStack,
        protected SmartyParser            $parser,
        protected TemplateHelperInterface $templateHelper
    ) {
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

        if ($view !== 'page' || !$viewId) {
            return;
        }

        $page = PageQuery::create()
            ->filterById($viewId)
            ->usePageTypeQuery('', Criteria::INNER_JOIN)
            ->endUse()
            ->findOne();

        if (!$page) {
            return;
        }

        $template = 'page-' . $page->getPageType()->getType() . '.html';

        if ($this->parser->templateExists($template)) {
            $request->attributes->set('_view', 'page-' . $page->getPageType()->getType());
        }

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
