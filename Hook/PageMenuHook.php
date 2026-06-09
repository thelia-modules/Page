<?php

namespace Page\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class PageMenuHook extends BaseHook
{
    public static function getSubscribedHooks(): array
    {
        return [
            "main.in-top-menu-items" => [
                [
                    "type" => "back",
                    "method" => "onMainInTopMenuItems"
                ],
            ],
            "module.configuration" => [
                [
                    "type" => "back",
                    "method" => "onModuleConfiguration"
                ],
            ],
        ];
    }

    public function onMainInTopMenuItems(HookRenderEvent $event): void
    {
        $event->add(
            $this->render('Page/hook/hook-in-top-menu-item.html.twig', $event->getTemplateVars())
        );
    }

    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        $event->add(
            $this->render('Page/hook/module-configuration.html.twig')
        );
    }
}
