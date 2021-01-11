<?php

namespace SilverStripe\SearchService\Admin;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\SearchService\Interfaces\IndexingInterface;

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

        if ($externalURL || $docsURL) {
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

        $form->setFields(FieldList::create($fields));

        return $form;

    }


}
