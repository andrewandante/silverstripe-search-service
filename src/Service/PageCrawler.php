<?php

namespace SilverStripe\SearchService\Service;

use DOMDocument;
use DOMXPath;
use Exception;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\Requirements;
use SilverStripe\View\Requirements_Backend;

/**
 * Fetches the main content off the page to index. This handles more complex
 * templates. Main content should be low-weighted as depending on your
 * front-end the <main> element may contain other information which should
 * not be indexed.
 *
 * @todo allow filtering
 */
class PageCrawler
{
    use Configurable;

    private $item;

    /**
     * Defines the xpath selector for the first element of content
     * that should be indexed.
     *
     * @config
     * @var string
     */
    private static $content_xpath_selector = '//main';

    /**
     * @param DataObject $item
     * @return string
     */
    public function getMainContent(DataObject $item)
    {
        if (!$item->hasMethod('Link')) {
            return '';
        }

        $page = null;

        Environment::increaseMemoryLimitTo();
        Environment::increaseTimeLimitTo();

        try {
            $response = Director::test($item->AbsoluteLink());
            $page = $response->getBody();
        } catch (Exception $e) {
            Injector::inst()->create(LoggerInterface::class)->error($e);
        }
        $output = '';
        // just get the internal content for the page.
        if ($page) {
            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadHTML($page);
            $xpath = new DOMXPath($dom);
            $selector = $this->config()->get('content_xpath_selector');
            $nodes = $xpath->query($selector);

            if (isset($nodes[0])) {
                $output = $nodes[0]->nodeValue;
            }
        }

        return $output;
    }
}
