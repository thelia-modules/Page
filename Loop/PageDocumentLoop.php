<?php

namespace Page\Loop;

use Imagick;
use ImagickException;
use Page\Model\PageDocument;
use Page\Model\PageDocumentQuery;
use Page\Model\PageTypeQuery;
use Page\Page;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Exception\PropelException;
use Thelia\Core\Event\Document\DocumentEvent;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Exception\ImageException;
use Thelia\Log\Tlog;
use Thelia\Type\BooleanOrBothType;
use Thelia\Type\EnumListType;
use Thelia\Type\TypeCollection;

/**
 * @method getId()
 * @method getPageId()
 * @method getVisible()
 * @method getOrder()
 */
class PageDocumentLoop extends BaseI18nLoop implements PropelSearchLoopInterface
{
    /**
     * @throws PropelException
     */
    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var PageDocument $pageDocument */
        foreach ($loopResult->getResultDataCollection() as $pageDocument) {
            $loopResultRow = new LoopResultRow($pageDocument);

            $file = $pageDocument->getVirtualColumn('i18n_FILE');

            $documentEvent = new DocumentEvent();
            $sourceFilePath = sprintf('%s/%s', Page::getDocumentsUploadDir(), $file);

            $documentEvent->setSourceFilepath($sourceFilePath);
            $documentEvent->setCacheSubdirectory('page_document');

            $this->dispatcher->dispatch($documentEvent, TheliaEvents::DOCUMENT_PROCESS);

            $imageEvent = new ImageEvent();
            if (pathinfo($file, PATHINFO_EXTENSION) === 'pdf') {
                try {
                    $fileImageName = $this->getFirstPicturePdf(Page::getImagesUploadDir(), $sourceFilePath, $file);

                    $imageEvent->setSourceFilepath($fileImageName);
                    $imageEvent->setCacheSubdirectory('page_document');

                    $this->dispatcher->dispatch($imageEvent, TheliaEvents::IMAGE_PROCESS);
                } catch (ImagickException|ImageException $e) {
                    Tlog::getInstance()->error($e->getMessage());
                }
            }

            $loopResultRow
                ->set('ID', $pageDocument->getId())
                ->set('PAGE_ID', $pageDocument->getPageId())
                ->set('VISIBLE', $pageDocument->getVisible())
                ->set('POSITION', $pageDocument->getPosition())
                ->set('PAGE_DOCUMENT_PATH', $documentEvent->getDocumentPath())
                ->set('PAGE_DOCUMENT_PDF_IMAGE_SOURCE', $imageEvent->getFileUrl())
                ->set('PAGE_DOCUMENT_FILE', $pageDocument->getVirtualColumn('i18n_FILE'))
                ->set('PAGE_DOCUMENT_TITLE', $pageDocument->getVirtualColumn('i18n_TITLE'))
                ->set('PAGE_DOCUMENT_DESCRIPTION', $pageDocument->getVirtualColumn('i18n_DESCRIPTION'))
                ->set('PAGE_DOCUMENT_CHAPO', $pageDocument->getVirtualColumn('i18n_CHAPO'))
                ->set('PAGE_DOCUMENT_POSTSCRIPTUM', $pageDocument->getVirtualColumn('i18n_POSTSCRIPTUM'))
            ;

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

    protected function getArgDefinitions(): ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntTypeArgument('id'),
            Argument::createIntListTypeArgument('page_id'),
            Argument::createBooleanOrBothTypeArgument('visible', 1),
            new Argument(
                'order',
                new TypeCollection(
                    new EnumListType(['alpha', 'alpha-reverse', 'id'])
                ),
                'position'
            ),
        );
    }

    public function buildModelCriteria(): PageTypeQuery|ModelCriteria
    {
        $search = PageDocumentQuery::create();
        $this->configureI18nProcessing($search, ['FILE', 'TITLE', 'DESCRIPTION', 'CHAPO', 'POSTSCRIPTUM']);

        if (null !== $id = $this->getId()) {
            $search->filterById($id, Criteria::IN);
        }
        if (null !== $pageId = $this->getPageId()) {
            $search->filterByPageId($pageId, Criteria::IN);
        }
        if (BooleanOrBothType::ANY !== $visible = $this->getVisible()) {
            $search->filterByVisible($visible ? 1 : 0);
        }

        return $search;
    }

    /**
     * @throws ImagickException
     */
    private function getFirstPicturePdf($sourceFirstPicturePath, $sourceImagePath, $fileName): string
    {
        if (!is_dir($sourceFirstPicturePath) && !mkdir($sourceFirstPicturePath, 0775, true) && !is_dir($sourceFirstPicturePath)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $sourceFirstPicturePath));
        }

        $fileImageName = $sourceFirstPicturePath .DS. $fileName. '.jpg';

        if (!file_exists($fileImageName)) {
            $pdfImage = new Imagick();

            $pdfImage->readImage($sourceImagePath.'[0]');
            $pdfImage->setFormat('jpg');
            $pdfImage->writeImage($fileImageName);
        }

        return $fileImageName;
    }
}