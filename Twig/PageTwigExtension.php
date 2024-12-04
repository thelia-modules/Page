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

namespace Page\Twig;

use Page\EventListener\KernelViewListener;
use TheliaBlocks\Service\JsonBlockService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PageTwigExtension extends AbstractExtension
{
    public function __construct(
        private JsonBlockService $jsonBlockService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getPageData', [$this, 'getPageData']),
        ];
    }

    public function getPageData()
    {
        return KernelViewListener::$page;
    }
}
