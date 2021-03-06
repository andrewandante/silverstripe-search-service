<?php


namespace SilverStripe\SearchService\Service\Traits;

use SilverStripe\SearchService\Service\IndexConfiguration;

trait ConfigurationAware
{
    /**
     * @var IndexConfiguration
     */
    private $configuration;

    /**
     * @param IndexConfiguration $config
     * @return $this
     */
    public function setConfiguration(IndexConfiguration $config): self
    {
        $this->configuration = $config;
        return $this;
    }

    /**
     * @return IndexConfiguration
     */
    public function getConfiguration(): IndexConfiguration
    {
        return $this->configuration;
    }
}
