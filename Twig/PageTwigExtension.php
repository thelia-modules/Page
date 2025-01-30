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
use Page\Model\PageI18nQuery;
use Page\Model\PageQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\HttpFoundation\RequestStack;
use TheliaBlocks\Service\JsonBlockService;
use TheliaLibrary\Model\LibraryItemImageQuery;
use TheliaLibrary\Service\LibraryImageService;
use TheliaLibrary\Twig\LibraryImage;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PageTwigExtension extends AbstractExtension
{
    public function __construct(
        private JsonBlockService $jsonBlockService,
        private LibraryImageService $libraryImageService,
        private LibraryImage $theliaLibraryTwigImage,
        protected RequestStack $requestStack,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('getPageData', [$this, 'getPageData']),
            new TwigFunction('getPageList', [$this, 'getPageList'])
        ];
    }

    public function getPageData()
    {
        return KernelViewListener::$page;
    }

    public function getPageList(?array $params = [])
    {
        $query = PageQuery::create();

        if (array_key_exists('parent_tree_level', $params)) {
            $query->filterByTreeLevel($params['parent_tree_level']);
        }
        if (array_key_exists('code', $params)) {
            $query->filterByCode($params['code']);
        }
        if (array_key_exists('id', $params)) {
            $query->filterById($params['id']);
        }
        if (array_key_exists('exclude_id', $params)) {
            $query->filterById($params['exclude_id'], Criteria::NOT_IN);
        }
        if (array_key_exists('tag', $params)) {
            $query
                ->usePageTagCombinationQuery()
                ->usePageTagQuery()
                ->filterByTag($params['tag'], Criteria::IN)
                ->endUse()
                ->endUse();
        }

        $pages = $query->filterByVisible(1);

        $results = [];

        foreach ($pages as $page) {
            $pageI18nQuery = PageI18nQuery::create();
            $imageTitle = $pageI18nQuery->filterById($page->getId())->findOne();

            $results[] = [
                'CODE' => $page->getCode(),
                'ID' => $page->getId(),
                'TITLE' =>  $imageTitle->getTitle(),
                'URL' => $page->getUrl(),
            ];
        }

        return $results;
    }
}
