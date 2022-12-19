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

namespace Page\Command;

use Page\Model\PageImageQuery;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Thelia\Command\ContainerAwareCommand;
use Thelia\Model\ConfigQuery;
use Thelia\Model\LangQuery;
use TheliaLibrary\Service\LibraryItemImageService;

class ImageMigrateCommand extends ContainerAwareCommand
{
    protected LibraryItemImageService $libraryItemImageService;

    public function __construct(LibraryItemImageService $libraryItemImageService)
    {
        parent::__construct();
        $this->libraryItemImageService = $libraryItemImageService;
    }

    protected function configure(): void
    {
        $this
            ->setName('library:page-image:migrate')
            ->setDescription('Migrate page image for TheliaLibrary');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filesystem = new Filesystem();


        $baseSourceFilePath = ConfigQuery::read('images_library_path');
        if ($baseSourceFilePath === null) {
            $baseSourceFilePath = THELIA_LOCAL_DIR.'media'.DS.'images';
        } else {
            $baseSourceFilePath = THELIA_ROOT.$baseSourceFilePath;
        }

        $langs = LangQuery::create()
            ->find();

        $output->writeln('<bg=blue>============================================================= </>');
        $output->writeln("<fg=blue>Image migration for item type page started</>");


        /** @var ModelCriteria $query */
        $images = PageImageQuery::create()->find();
        $output->writeln(\count($images)." image found for type page");
        $progressBar = new ProgressBar($output, \count($images));
        $progressBar->start();

        foreach ($images as $image) {
            $tmpFilePath = '/tmp/image/'.$image->getFile();
            $filesystem->copy($baseSourceFilePath.DS.'page'.DS.$image->getFile(), $tmpFilePath);
            $uploadedFile = new File(
                $tmpFilePath
            );

            $itemImage = $this->libraryItemImageService->createAndAssociateImage(
                $uploadedFile,
                $image->getTitle(),
                $image->getLocale(),
                'page',
                $image->getPageId(),
                'thelia',
                $image->getVisible(),
                $image->getPosition()
            );
            $libraryImage = $itemImage->getLibraryImage();
            $libraryFilePath = $libraryImage->getFileName();

            foreach ($langs as $lang) {
                $image->setLocale($lang->getLocale());

                if (empty($image->getTitle())) {
                    continue;
                }

                $libraryImage->setLocale($lang->getLocale())
                    ->setTitle($image->getTitle())
                    ->setFileName($libraryFilePath)
                    ->save();
            }
            $progressBar->advance();
        }
        $progressBar->finish();
        $output->writeln('');
        $output->writeln("<info>Image migration for item type page ended</info>");
        $output->writeln('<bg=blue>============================================================= </>');


        return 1;
    }
}
