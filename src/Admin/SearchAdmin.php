<?php

namespace SilverStripe\SearchService\Admin;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBInt;
use SilverStripe\ORM\FieldType\DBText;
use SilverStripe\SearchService\Extensions\SearchServiceExtension;
use SilverStripe\SearchService\Interfaces\DocumentFetcherInterface;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use SilverStripe\SearchService\Services\AppSearch\AppSearchService;
use SilverStripe\View\ArrayData;
use Symfony\Component\VarDumper\Cloner\Data;

class SearchAdmin extends LeftAndMain
{
    private static $url_segment = 'search-service';

    private static $menu_title = 'Search Service';

    private static $menu_icon_class = 'font-icon-search';

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        /** @var IndexingInterface $indexService */
        $indexService = Injector::inst()->get(IndexingInterface::class);

        $fields = [];
        $externalURL = $indexService->getExternalURL();
        $docsURL = $indexService->getDocumentationURL();

        if ($externalURL !== null || $docsURL !== null) {
            $fields[] = HeaderField::create('ExternalLinksHeader', 'External Links')
                ->setAttribute('style', 'font-weight: 300;');

            if ($externalURL !== null) {
                $fields[] = LiteralField::create(
                    'ExternalURL',
                    sprintf(
                        '<div><a href="%s" target="_blank" style="font-size: large">%s</a></div>',
                        $externalURL,
                        $indexService->getExternalURLDescription() ?? 'External URL'
                    ),
                );
            }

            if ($docsURL !== null) {
                $fields[] = LiteralField::create(
                    'DocsURL',
                    sprintf('<div><a href="%s" target="_blank" style="font-size: large">Documentation URL</a></div>', $docsURL),
                );
            }

            $fields[] = LiteralField::create('Divider', '<div class="clear" style="height: 32px; border-bottom: 1px solid #ced5e1"></div>');
        }

        $indexedDocsGridfield = GridField::create(
            'IndexedDocuments',
            'Documents by Index',
            $this->buildIndexedDocumentsList()
        );

        $fields[] = $indexedDocsGridfield;

        return $form->setFields(FieldList::create($fields));
    }

    private function buildIndexedDocumentsList()
    {
        $list = ArrayList::create();

        /** @var IndexingInterface $indexer */
        $indexer = Injector::inst()->get(IndexingInterface::class);

        $configuration = SearchServiceExtension::singleton()->getConfiguration();
        foreach ($configuration->getIndexes() as $index => $data) {

            $localCount = 0;
            foreach ($configuration->getClassesForIndex($index) as $class) {
                $localCount += $class::get()->where('SearchIndexed IS NOT NULL')->count();
            }

            $result = new IndexedDocumentsResult();
            $result->IndexName = AppSearchService::environmentizeIndex($index);
            $result->DBDocs = $localCount;
            $result->RemoteDocs = $indexer->getDocumentTotal($index);
            $list->push($result);
        }

        return $list;
    }


}
