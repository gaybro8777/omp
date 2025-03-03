<?php
/**
 * @file components/listPanels/CatalogListPanel.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CatalogListPanel
 * @ingroup classes_components_listPanels
 *
 * @brief Instantiates and manages a UI component to list catalog entries.
 */

namespace APP\components\listPanels;

use \PKP\submission\PKPSubmissionDAO;
use \PKP\submission\PKPSubmission;

use \APP\core\Application;
use \APP\i18n\AppLocale;
use \APP\template\TemplateManager;

class CatalogListPanel extends \PKP\components\listPanels\ListPanel
{
    /** @var string URL to the API endpoint where items can be retrieved */
    public $apiUrl = '';

    /** @var integer Number of items to show at one time */
    public $count = 30;

    /** @var array Query parameters to pass if this list executes GET requests  */
    public $getParams = [];

    /** @var integer Count of total items available for list */
    public $itemsMax = 0;

    /**
     * @copydoc ListPanel::getConfig()
     */
    public function getConfig()
    {
        AppLocale::requireComponents(LOCALE_COMPONENT_APP_SUBMISSION);

        $request = Application::get()->getRequest();
        $context = $request->getContext();

        [$catalogSortBy, $catalogSortDir] = explode('-', $context->getData('catalogSortOption'));
        $catalogSortBy = empty($catalogSortBy) ? PKPSubmissionDAO::ORDERBY_DATE_PUBLISHED : $catalogSortBy;
        $catalogSortDir = $catalogSortDir == SORT_DIRECTION_ASC ? 'ASC' : 'DESC';
        $config['catalogSortBy'] = $catalogSortBy;
        $config['catalogSortDir'] = $catalogSortDir;

        $this->getParams = array_merge(
            $this->getParams,
            [
                'status' => PKPSubmission::STATUS_PUBLISHED,
                'orderByFeatured' => true,
                'orderBy' => $catalogSortBy,
                'orderDirection' => $catalogSortDir,
            ]
        );

        $config = parent::getConfig();

        $config['apiUrl'] = $this->apiUrl;
        $config['count'] = $this->count;
        $config['getParams'] = $this->getParams;
        $config['itemsMax'] = $this->itemsMax;

        $config['filters'] = [];

        if ($context) {
            $config['contextId'] = $context->getId();

            $categories = [];
            $categoryDao = \DAORegistry::getDAO('CategoryDAO');
            $categoriesResult = $categoryDao->getByContextId($context->getId());
            while (!$categoriesResult->eof()) {
                $category = $categoriesResult->next();
                [$categorySortBy, $categorySortDir] = explode('-', $category->getSortOption());
                $categorySortDir = empty($categorySortDir) ? $catalogSortDir : $categorySortDir == SORT_DIRECTION_ASC ? 'ASC' : 'DESC';
                $categories[] = [
                    'param' => 'categoryIds',
                    'value' => (int) $category->getId(),
                    'title' => $category->getLocalizedTitle(),
                    'sortBy' => $categorySortBy,
                    'sortDir' => $categorySortDir,
                ];
            }
            if (count($categories)) {
                $config['filters'][] = [
                    'heading' => __('catalog.categories'),
                    'filters' => $categories,
                ];
            }

            $series = [];
            $seriesDao = \DAORegistry::getDAO('SeriesDAO');
            $seriesResult = $seriesDao->getByPressId($context->getId());
            while (!$seriesResult->eof()) {
                $seriesObj = $seriesResult->next();
                [$seriesSortBy, $seriesSortDir] = explode('-', $seriesObj->getSortOption());
                $seriesSortDir = empty($seriesSortDir) ? $catalogSortDir : $seriesSortDir == SORT_DIRECTION_ASC ? 'ASC' : 'DESC';
                $series[] = [
                    'param' => 'seriesIds',
                    'value' => (int) $seriesObj->getId(),
                    'title' => $seriesObj->getLocalizedTitle(),
                    'sortBy' => $seriesSortBy,
                    'sortDir' => $seriesSortDir,
                ];
            }
            if (count($series)) {
                $config['filters'][] = [
                    'heading' => __('catalog.manage.series'),
                    'filters' => $series,
                ];
            }
        }

        // Attach a CSRF token for post requests
        $config['csrfToken'] = $request->getSession()->getCSRFToken();

        // Get the form to add a new entry
        $addEntryApiUrl = $request->getDispatcher()->url(
            $request,
            \PKPApplication::ROUTE_API,
            $context->getPath(),
            '_submissions/addToCatalog'
        );
        $searchSubmissionsApiUrl = $request->getDispatcher()->url(
            $request,
            \PKPApplication::ROUTE_API,
            $context->getPath(),
            'submissions'
        );
        $supportedFormLocales = $context->getSupportedFormLocales();
        $localeNames = AppLocale::getAllLocales();
        $locales = array_map(function ($localeKey) use ($localeNames) {
            return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
        }, $supportedFormLocales);
        $addEntryForm = new \APP\components\forms\catalog\AddEntryForm($addEntryApiUrl, $searchSubmissionsApiUrl, $locales);
        $config['addEntryForm'] = $addEntryForm->getConfig();

        $templateMgr = \TemplateManager::getManager($request);
        $templateMgr->setConstants([
            'ASSOC_TYPE_PRESS',
            'ASSOC_TYPE_CATEGORY',
            'ASSOC_TYPE_SERIES',
        ]);

        $templateMgr->setLocaleKeys([
            'submission.catalogEntry.new',
            'submission.list.saveFeatureOrder',
            'submission.list.orderFeatures',
            'catalog.manage.categoryFeatured',
            'catalog.manage.seriesFeatured',
            'catalog.manage.featured',
            'catalog.manage.feature.categoryNewRelease',
            'catalog.manage.feature.seriesNewRelease',
            'catalog.manage.feature.newRelease',
            'submission.list.orderingFeatures',
            'submission.list.orderingFeaturesSection',
            'catalog.manage.isFeatured',
            'catalog.manage.isNotFeatured',
            'catalog.manage.isNewRelease',
            'catalog.manage.isNotNewRelease',
            'submission.list.viewEntry',
            'submission.list.viewSubmission',
        ]);

        return $config;
    }
}
