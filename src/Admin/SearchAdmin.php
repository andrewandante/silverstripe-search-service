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
use SilverStripe\SearchService\Interfaces\IndexingInterface;
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
            $fields[] = HeaderField::create('ExternalLinksHeader', 'External Links');
        }

        if ($externalURL !== null) {
            $fields[] = LiteralField::create(
                'ExternalURL',
                sprintf(
                    '<div><a href="%s" target="_blank">%s</a></div>',
                    $externalURL,
                    $indexService->getExternalURLDescription() ?? 'External URL'
                ),
            );
        }

        if ($docsURL !== null) {
            $fields[] = LiteralField::create(
                'DocsURL',
                sprintf('<div><a href="%s" target="_blank">Documentation URL</a></div>', $docsURL),
            );
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
        foreach (SearchServiceExtension::singleton()->getConfiguration()->getIndexes() as $index => $data) {
            $dataObject = new IndexedDocumentsResult();
            $dataObject->IndexName = $index;
            $dataObject->DBDocs = 123;
            $dataObject->RemoteDocs = 456;
            $list->push($dataObject);
        }

        return $list;
    }


}
