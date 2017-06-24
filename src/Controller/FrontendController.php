<?php

namespace LuceneSearchBundle\Controller;

use LuceneSearchBundle\Config\ConfigManager;
use LuceneSearchBundle\Helper\LuceneHelper;
use LuceneSearchBundle\Helper\StringHelper;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;

class FrontendController
{
    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var ConfigManager
     */
    protected $configManager;

    /**
     * @var EngineInterface
     */
    protected $templating;

    /**
     * @var LuceneHelper
     */
    protected $luceneHelper;

    /**
     * @var StringHelper
     */
    private $stringHelper;

    /**
     * @var string
     */
    protected $frontendIndex;

    /**
     * @var array
     */
    protected $categories = [];

    /**
     * query, incoming argument
     * @var String
     */
    protected $query = '';

    /**
     * query, incoming argument, unmodified
     * @var String
     */
    protected $untouchedQuery = '';

    /**
     * category, to restrict query, incoming argument
     * @var array
     */
    protected $category = '';

    /**
     * @var string
     */
    protected $searchLanguage = NULL;

    /**
     * @var string
     */
    protected $searchCountry = NULL;

    /**
     * @var bool
     */
    protected $searchRestriction = FALSE;

    /**
     * @var bool
     */
    protected $ownHostOnly = FALSE;

    /**
     * @var bool
     */
    protected $fuzzySearch = FALSE;

    /**
     * @var int
     */
    protected $maxSuggestions = 10;

    /**
     * @var int
     */
    protected $perPage = 10;

    /**
     * @var int
     */
    protected $currentPage = 1;

    /**
     * FrontendController constructor.
     *
     * @param RequestStack    $requestStack
     * @param EngineInterface $templating
     * @param ConfigManager   $configManager
     * @param LuceneHelper    $luceneHelper
     * @param StringHelper    $stringHelper
     *
     * @throws \Exception
     */
    public function __construct(
        RequestStack $requestStack,
        EngineInterface $templating,
        ConfigManager $configManager,
        LuceneHelper $luceneHelper,
        StringHelper $stringHelper
    ) {
        $this->requestStack = $requestStack;
        $this->templating = $templating;
        $this->configManager = $configManager;
        $this->luceneHelper = $luceneHelper;
        $this->stringHelper = $stringHelper;

        $requestQuery = $this->requestStack->getMasterRequest()->query;

        if (!$this->configManager->getConfig('enabled')) {
            return FALSE;
        }

        try {

            \Zend_Search_Lucene_Analysis_Analyzer::setDefault(
                new \Zend_Search_Lucene_Analysis_Analyzer_Common_Utf8Num_CaseInsensitive()
            );

            $this->frontendIndex = \Zend_Search_Lucene::open(ConfigManager::INDEX_DIR_PATH_STABLE);
            $this->categories = $this->configManager->getConfig('categories');

            //set search term query
            $searchQuery = $this->stringHelper->cleanRequestString($requestQuery->get('q'));

            if (!empty($searchQuery)) {
                $this->query = $this->luceneHelper->cleanTerm($searchQuery);
                $this->untouchedQuery = $this->query;
            }

            //set Language
            if ($this->configManager->getConfig('locale:ignore_language') === FALSE) {

                $requestLang = $requestQuery->get('language');

                //no language provided, try to get from requestStack.
                if (empty($requestLang)) {
                    $masterRequest = $this->requestStack->getMasterRequest();
                    if ($masterRequest) {
                        $this->searchLanguage = $this->requestStack->getMasterRequest()->getLocale();
                    } else {
                        $this->searchLanguage = \Pimcore\Tool::getDefaultLanguage();
                    }
                } else {
                    $this->searchLanguage = $requestLang;
                }
            }

            //Set Category
            $queryCategory = $this->stringHelper->cleanRequestString($requestQuery->get('category'));

            if (!empty($queryCategory)) {
                $this->category = $queryCategory;
            }

            //Set Country
            if ($this->configManager->getConfig('locale:ignore_country') !== TRUE) {
                $this->searchCountry = $requestQuery->get('country');

                if ($this->searchCountry == 'global') {
                    $this->searchCountry = 'international';
                } else if (empty($this->searchCountry)) {
                    $this->searchCountry = 'international';
                }
            } else {
                $this->searchCountry = NULL;
            }

            //Set Restrictions (Auth)
            if ($this->configManager->getConfig('restriction:ignore') === FALSE) {
                $this->searchRestriction = TRUE;
            }

            //Set Fuzzy Search (Auth)
            $fuzzySearchRequest = $requestQuery->get('fuzzy');
            if ($this->configManager->getConfig('fuzzy_search') == TRUE || (!empty($fuzzySearchRequest)) && $fuzzySearchRequest !== 'false') {
                $this->fuzzySearch = TRUE;
            }

            //Set own Host Only
            if ($this->configManager->getConfig('own_host_only') == TRUE) {
                $this->ownHostOnly = TRUE;
            }

            //Set Entries per Page
            $this->perPage = $this->configManager->getConfig('view:max_per_page');
            $perPage = $requestQuery->get('perPage');
            if (!empty($perPage)) {
                $this->perPage = (int)$perPage;
            }

            //Set max Suggestions
            $this->maxSuggestions = $this->configManager->getConfig('view:max_suggestions');

            //Set Current Page
            $currentPage = $requestQuery->get('page');
            if (!empty($currentPage)) {
                $this->currentPage = (int)$currentPage;
            }
        } catch (\Exception $e) {
            throw new \Exception('could not open index');
        }
    }

    /**
     * @param $queryHits
     *
     * @return array
     */
    protected function getValidHits($queryHits)
    {
        $validHits = [];

        if ($this->ownHostOnly && $queryHits !== NULL) {
            //get rid of hits from other hosts
            $currentHost = \Pimcore\Tool::getHostname();

            foreach ($queryHits as $hit) {
                $url = $hit->getDocument()->getField('url');
                if (strpos($url->value, '://' . $currentHost) !== FALSE) {
                    $validHits[] = $hit;
                }
            }
        } else {
            $validHits = $queryHits;
        }

        return $validHits;
    }

    /**
     * @param $query
     *
     * @return mixed
     */
    protected function addCountryQuery($query)
    {
        if (!empty($this->searchCountry)) {

            $countryQuery = new \Zend_Search_Lucene_Search_Query_MultiTerm();
            $countryQuery->addTerm(new \Zend_Search_Lucene_Index_Term('all', 'country'));

            $country = str_replace(['_', '-'], '', $this->searchCountry);
            $countryQuery->addTerm(new \Zend_Search_Lucene_Index_Term($country, 'country'));

            $query->addSubquery($countryQuery, TRUE);
        }

        return $query;
    }

    /**
     * @param $query
     *
     * @return mixed
     */
    protected function addCategoryQuery($query)
    {
        if (!empty($this->category)) {
            $categoryTerm = new \Zend_Search_Lucene_Index_Term($this->category, 'cat');
            $categoryQuery = new \Zend_Search_Lucene_Search_Query_Term($categoryTerm);
            $query->addSubquery($categoryQuery, TRUE);
        }

        return $query;
    }

    /**
     * @param $query
     *
     * @return mixed
     */
    protected function addLanguageQuery($query)
    {
        if (!empty($this->searchLanguage)) {
            $languageQuery = new \Zend_Search_Lucene_Search_Query_MultiTerm();
            $languageQuery->addTerm(new \Zend_Search_Lucene_Index_Term('all', 'lang'));

            if (is_object($this->searchLanguage)) {
                $lang = $this->searchLanguage->toString();
            } else {
                $lang = $this->searchLanguage;
            }

            $lang = strtolower(str_replace('_', '-', $lang));
            $languageQuery->addTerm(new \Zend_Search_Lucene_Index_Term($lang, 'lang'));

            $query->addSubquery($languageQuery, TRUE);
        }

        return $query;
    }

    /**
     * @param $query
     *
     * @return mixed
     * @throws \Exception
     */
    protected function addRestrictionQuery($query)
    {
        if ($this->searchRestriction) {
            $restrictionTerms = [
                new \Zend_Search_Lucene_Index_Term(TRUE, 'restrictionGroup_default')
            ];

            $signs = [NULL];

            $class = $this->configManager->getConfig('restriction:class');
            $method = $this->configManager->getConfig('restriction:method');

            $call = [$class, $method];

            if (is_callable($call, FALSE)) {
                $allowedGroups = call_user_func($call);

                if (is_array($allowedGroups)) {
                    foreach ($allowedGroups as $group) {
                        $restrictionTerms[] = new \Zend_Search_Lucene_Index_Term(TRUE, 'restrictionGroup_' . $group);
                        $signs[] = NULL;
                    }
                }

                $restrictionQuery = new \Zend_Search_Lucene_Search_Query_MultiTerm($restrictionTerms, $signs);
                $query->addSubquery($restrictionQuery, TRUE);
            } else {
                throw new \Exception('Method "' . $method . '" in "' . $class . '" not callable');
            }
        }

        return $query;
    }

}