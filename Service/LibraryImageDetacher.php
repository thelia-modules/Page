<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Page\Service;

use TheliaLibrary\Model\LibraryItemImage;
use TheliaLibrary\Model\LibraryItemImageQuery;
use TheliaLibrary\Service\LibraryImageService;
use TheliaLibrary\Service\LibraryItemImageService;

/**
 * Removes one association between a library image and an item. The library
 * image itself, with its file, goes only when no other item uses it anymore:
 * the library is shared by pages, products, categories and the other modules.
 */
final readonly class LibraryImageDetacher
{
    public function __construct(
        private LibraryItemImageService $libraryItemImageService,
        private LibraryImageService $libraryImageService,
    ) {
    }

    public function detach(LibraryItemImage $itemImage): void
    {
        $imageId = $itemImage->getImageId();

        $this->libraryItemImageService->deleteImageAssociation($itemImage->getId());

        if (null === $imageId || LibraryItemImageQuery::create()->filterByImageId($imageId)->exists()) {
            return;
        }

        $this->libraryImageService->deleteImage($imageId);
    }
}
