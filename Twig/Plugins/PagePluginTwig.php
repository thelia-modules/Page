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

namespace Page\Twig\Plugins;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

use Thelia\Service\Model\LangService;
use Thelia\TaxEngine\TaxEngine;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class BetterSeoMicroDataPluginTwig extends AbstractExtension
{
    public function __construct(private Environment $twig, private RequestStack $requestStack, private EventDispatcherInterface $dispatcher, private TaxEngine $taxEngine, private LangService $langService)
    {
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('PageData', [$this, 'pageData']),
            
        ];
    }

    public function BetterSeoPageTitle()
    {
        return 'page';
    }

    public function pageData(?string $view, ?array $params)
    {

    }

}
