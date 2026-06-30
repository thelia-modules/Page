<?php

namespace Page\Smarty\Plugins;

use Symfony\Component\HttpFoundation\RequestStack;
use TheliaSmarty\Template\AbstractSmartyPlugin;
use TheliaSmarty\Template\SmartyPluginDescriptor;

class PagePlugin extends AbstractSmartyPlugin
{
    public function __construct(protected RequestStack $requestStack)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getPluginDescriptors(): array
    {
        return [
            new SmartyPluginDescriptor('function', 'page', $this, 'pageDataAccess')
        ];
    }

    public function pageDataAccess($params, &$smarty)
    {
        $request = $this->requestStack->getCurrentRequest();
        $pageId = $request->attributes->get('page_id', $request->query->get('page_id', $request->request->get('page_id')));

        if ($pageId !== null) {
            return $pageId;
        }

        return '';
    }
}