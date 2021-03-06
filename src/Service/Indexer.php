<?php


namespace SilverStripe\SearchService\Service;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\SearchService\Interfaces\DependencyTracker;
use SilverStripe\SearchService\Interfaces\DocumentAddHandler;
use SilverStripe\SearchService\Interfaces\DocumentInterface;
use SilverStripe\SearchService\Interfaces\DocumentRemoveHandler;
use SilverStripe\SearchService\Interfaces\IndexingInterface;
use InvalidArgumentException;
use SilverStripe\SearchService\Service\Traits\ConfigurationAware;
use SilverStripe\SearchService\Service\Traits\ServiceAware;

class Indexer
{
    use Injectable;
    use Configurable;
    use ConfigurationAware;
    use ServiceAware;


    const METHOD_DELETE = 0;

    const METHOD_ADD = 1;

    /**
     * @var array
     */
    private static $dependencies = [
        'IndexService' => '%$' . IndexingInterface::class,
    ];

    /**
     * @var bool
     */
    private $finished = false;

    /**
     * @var array
     */
    private $chunks = [];

    /**
     * @var DocumentInterface[]
     */
    private $documents = [];

    /**
     * @var int
     */
    private $batchSize;

    /**
     * @var int
     */
    private $method;

    /**
     * @var bool
     */
    private $processDependencies = true;

    /**
     * @var bool
     */
    private $isComplete = false;

    /**
     * Indexer constructor.
     * @param array $documents
     * @param int $method
     * @param int|null $batchSize
     */
    public function __construct(
        array $documents = [],
        int $method = self::METHOD_ADD,
        ?int $batchSize = null
    ) {
        $this->setConfiguration(IndexConfiguration::singleton());
        $this->setMethod($method);
        $this->setBatchSize($batchSize ?: $this->getConfiguration()->getBatchSize());
        $this->setProcessDependencies($this->getConfiguration()->shouldTrackDependencies());
        $this->setDocuments($documents);
    }

    /**
     * @return void
     */
    public function processNode(): void
    {
        $remainingChildren = $this->chunks;
        $documents = array_shift($remainingChildren);
        if ($this->method === static::METHOD_DELETE) {
            $this->getIndexService()->removeDocuments($documents);
        } else {
            $toRemove = [];
            $toUpdate = [];
            /* @var DocumentInterface $document */
            foreach ($documents as $document) {
                if (!$this->getConfiguration()->isClassIndexed($document->getSourceClass())) {
                    continue;
                }
                if ($document->shouldIndex()) {
                    if ($document instanceof DocumentAddHandler) {
                        $document->onAddToSearchIndexes(DocumentAddHandler::BEFORE_ADD);
                    }
                    $toUpdate[] = $document;
                } else {
                    if ($document instanceof DocumentRemoveHandler) {
                        $document->onRemoveFromSearchIndexes(DocumentRemoveHandler::BEFORE_REMOVE);
                    }
                    $toRemove[] = $document;
                }
            }
            if (!empty($toUpdate)) {
                $this->getIndexService()->addDocuments($toUpdate);
                foreach ($toUpdate as $document) {
                    if ($document instanceof DocumentAddHandler) {
                        $document->onAddToSearchIndexes(DocumentAddHandler::AFTER_ADD);
                    }
                }
            }
            if (!empty($toRemove)) {
                $this->getIndexService()->removeDocuments($toRemove);
                foreach ($toRemove as $document) {
                    if ($document instanceof DocumentRemoveHandler) {
                        $document->onRemoveFromSearchIndexes(DocumentRemoveHandler::AFTER_REMOVE);
                    }
                }
            }
        }

        $this->chunks = $remainingChildren;

        if ($this->processDependencies) {
            foreach ($documents as $document) {
                if ($document instanceof DependencyTracker) {
                    $dependentDocs = array_filter(
                        $document->getDependentDocuments(),
                        function (DocumentInterface $dependentDocument) use ($document) {
                            return $dependentDocument->getIdentifier() !== $document->getIdentifier();
                        }
                    );
                    if (!empty($dependentDocs)) {
                        $child = Indexer::create($dependentDocs, self::METHOD_ADD, $this->getBatchSize());
                        while (!$child->finished()) {
                            $child->processNode();
                        }
                    }
                }
            }
        }

        if (!count($remainingChildren)) {
            $this->isComplete = true;
            return;
        }
    }

    /**
     * @param $method
     * @return $this
     */
    public function setMethod($method): Indexer
    {
        if (!in_array($method, [self::METHOD_ADD, self::METHOD_DELETE])) {
            throw new InvalidArgumentException(sprintf(
                'Invalid method: %s',
                $method
            ));
        }

        $this->method = $method;

        return $this;
    }

    /**
     * @return int
     */
    public function getMethod(): int
    {
        return $this->method;
    }

    /**
     * @param bool $processDependencies
     * @return Indexer
     */
    public function setProcessDependencies(bool $processDependencies): Indexer
    {
        $this->processDependencies = $processDependencies;
        return $this;
    }

    /**
     * @param int $batchSize
     * @return Indexer
     */
    public function setBatchSize(int $batchSize): Indexer
    {
        if ($batchSize < 1) {
            throw new InvalidArgumentException('Batch size must be greater than 0');
        }
        $this->batchSize = $batchSize;
        $this->chunks = array_chunk($this->documents, $batchSize);

        return $this;
    }

    /**
     * @return int
     */
    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    /**
     * @return bool
     */
    public function finished(): bool
    {
        return $this->isComplete;
    }

    /**
     * @return int
     */
    public function getChunkCount(): int
    {
        return sizeof($this->chunks);
    }

    /**
     * @return DocumentInterface[]
     */
    public function getDocuments(): array
    {
        return $this->documents;
    }

    /**
     * @param DocumentInterface[] $documents
     * @return Indexer
     */
    public function setDocuments(array $documents): Indexer
    {
        $this->documents = $documents;
        $this->chunks = array_chunk($this->documents, $this->getBatchSize());
        return $this;
    }
}
