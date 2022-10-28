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
        ];
    }

    public function onMainInTopMenuItems(HookRenderEvent $event): void
    {
        $event->add(
            $this->render('hook/hook-in-top-menu-item.html', $event->getTemplateVars())
        );
    }
}
