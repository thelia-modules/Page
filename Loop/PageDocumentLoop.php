<?php

namespace Page\Loop;

use Page\Model\PageDocument;
use Page\Model\PageDocumentQuery;
use Page\Model\PageTypeQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Exception\PropelException;
use Thelia\Core\Event\Document\DocumentEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Model\ConfigQuery;
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
        $baseSourceFilePath = ConfigQuery::read('documents_library_path');
        if ($baseSourceFilePath === null) {
            $baseSourceFilePath = THELIA_LOCAL_DIR.'media'.DS.'documents';
        } else {
            $baseSourceFilePath = THELIA_ROOT.$baseSourceFilePath;
        }

        /** @var PageDocument $pageDocument */
        foreach ($loopResult->getResultDataCollection() as $pageDocument) {
            $loopResultRow = new LoopResultRow($pageDocument);

            $event = new DocumentEvent();
            // Put source document file path
            $sourceFilePath = sprintf(
                '%s/%s/%s',
                $baseSourceFilePath,
                'page',
                $pageDocument->getVirtualColumn('i18n_FILE')
            );

            $event->setSourceFilepath($sourceFilePath);
            $event->setCacheSubdirectory('page_document');

            // Dispatch document processing event
            $this->dispatcher->dispatch($event, TheliaEvents::DOCUMENT_PROCESS);

            $path = $event->getDocumentPath();

            $loopResultRow
                ->set('ID', $pageDocument->getId())
                ->set('PAGE_ID', $pageDocument->getPageId())
                ->set('VISIBLE', $pageDocument->getVisible())
                ->set('POSITION', $pageDocument->getPosition())
                ->set('PAGE_DOCUMENT_PATH', $event->getDocumentPath())
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
}