<?php

namespace LuceneSearchBundle\Configuration;

use Pimcore\Extension\Bundle\PimcoreBundleManager;
use Symfony\Component\Filesystem\Filesystem;

class Configuration
{
    const STATE_DEFAULT_VALUES = [
        'forceStart' => FALSE,
        'forceStop'  => FALSE,
        'running'    => FALSE,
        'started'    => NULL,
        'finished'   => NULL
    ];

    const SYSTEM_CONFIG_FILE_PATH = PIMCORE_PRIVATE_VAR . '/bundles/LuceneSearchBundle/config.yml';

    const STATE_FILE_PATH = PIMCORE_PRIVATE_VAR . '/bundles/LuceneSearchBundle/state.cnf';

    const CRAWLER_LOG_FILE_PATH = PIMCORE_PRIVATE_VAR . '/bundles/LuceneSearchBundle/crawler.log';

    const CRAWLER_PROCESS_FILE_PATH = PIMCORE_PRIVATE_VAR . '/bundles/LuceneSearchBundle/processing.tmp';

    const CRAWLER_URI_FILTER_FILE_PATH = PIMCORE_PRIVATE_VAR . '/bundles/LuceneSearchBundle/uri-filter.tmp';

    const SITEMAP_DIR_PATH = PIMCORE_PRIVATE_VAR . '/bundles/LuceneSearchBundle/site-map';

    const CRAWLER_PERSISTENCE_STORE_DIR_PATH = PIMCORE_PRIVATE_VAR . '/bundles/LuceneSearchBundle/persistence-store';

    const CRAWLER_TMP_ASSET_DIR_PATH = PIMCORE_PRIVATE_VAR . '/bundles/LuceneSearchBundle/tmp-assets';

    const INDEX_DIR_PATH = PIMCORE_PRIVATE_VAR . '/bundles/LuceneSearchBundle/index';

    const INDEX_DIR_PATH_GENESIS = PIMCORE_PRIVATE_VAR . '/bundles/LuceneSearchBundle/index/genesis';

    const INDEX_DIR_PATH_STABLE = PIMCORE_PRIVATE_VAR . '/bundles/LuceneSearchBundle/index/stable';

    /**
     * @var PimcoreBundleManager
     */
    protected $bundleManager;

    /**
     * @var Filesystem
     */
    private $fileSystem;

    /**
     * @var array
     */
    private $config;

    /**
     * @var array
     */
    private $systemConfig;

    /**
     * @var array
     */
    private $categories;

    /**
     * Configuration constructor.
     *
     * @param PimcoreBundleManager $bundleManager
     */
    public function __construct(PimcoreBundleManager $bundleManager)
    {
        $this->bundleManager = $bundleManager;
        $this->fileSystem = new FileSystem();
    }

    /**
     * @param array $config
     */
    public function setConfig($config = [])
    {
        $this->config = $config;
    }

    /**
     * @param $slot
     *
     * @return mixed
     */
    public function getConfig($slot)
    {
        return $this->config[$slot];
    }

    /**
     * @param array $config
     */
    public function setSystemConfig($config = [])
    {
        $this->systemConfig = $config;
    }

    /**
     * @param null $slot
     *
     * @return mixed
     */
    public function getSystemConfig($slot = NULL)
    {
        return $this->systemConfig[$slot];
    }

    /**
     * @param null $slot
     *
     * @return mixed
     */
    public function getStateConfig($slot = NULL)
    {
        $data = file_get_contents(self::STATE_FILE_PATH);
        $arrayData = unserialize($data);

        return $slot == NULL ? $arrayData : $arrayData[$slot];
    }

    /**
     * @param $slot
     * @param $value
     *
     * @throws \Exception
     */
    public function setStateConfig($slot, $value)
    {
        $content = $this->getStateConfig();

        if (!in_array($slot, array_keys($content))) {
            throw new \Exception('invalid state config slot "' . $slot . '"');
        }

        $content[$slot] = $value;

        $this->fileSystem->dumpFile(self::STATE_FILE_PATH, serialize($content));
    }

    /**
     * @param array $categories
     */
    public function setCategories(array $categories)
    {
        $this->categories = $categories;
    }

    /**
     * @return array
     */
    public function getCategories()
    {
        return $this->categories;
    }

    /**
     * @param string $bundleName
     *
     * @return bool
     */
    public function hasBundle($bundleName = 'ExtensionBundle\ExtensionBundle')
    {
        try {
            $hasExtension = $this->bundleManager->isEnabled($bundleName);
        } catch (\Exception $e) {
            $hasExtension = FALSE;
        }

        return $hasExtension;
    }
}