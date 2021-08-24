<?php

/**
 * @file pages/stats/PKPStatsHandler.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsHandler
 * @ingroup pages_stats
 *
 * @brief Handle requests for statistics pages.
 */

use APP\core\Application;
use APP\core\Request;
use APP\core\Services;
use APP\facades\Repo;

use APP\handler\Handler;

use APP\template\TemplateManager;
use PKP\plugins\PluginRegistry;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;
use PKP\statistics\PKPStatisticsHelper;

class PKPStatsHandler extends Handler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR],
            ['editorial', 'publications', 'users', 'reports']
        );
    }

    /**
     * @see PKPHandler::authorize()
     *
     * @param PKPRequest $request
     * @param array $args
     * @param array $roleAssignments
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }


    //
    // Public handler methods.
    //
    /**
     * Display editorial stats about the submission workflow process
     *
     * @param array $args
     * @param Request $request
     */
    public function editorial($args, $request)
    {
        $dispatcher = $request->getDispatcher();
        $context = $request->getContext();

        if (!$context) {
            $dispatcher->handle404();
        }

        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);

        $dateStart = date('Y-m-d', strtotime('-91 days'));
        $dateEnd = date('Y-m-d', strtotime('yesterday'));

        $args = [
            'contextIds' => [$context->getId()],
        ];

        $totals = Services::get('editorialStats')->getOverview($args);
        $averages = Services::get('editorialStats')->getAverages($args);
        $dateRangeTotals = Services::get('editorialStats')->getOverview(
            array_merge(
                $args,
                [
                    'dateStart' => $dateStart,
                    'dateEnd' => $dateEnd,
                ]
            )
        );

        // Stats that should be converted to percentages
        $percentageStats = [
            'acceptanceRate',
            'declineRate',
            'declinedDeskRate',
            'declinedReviewRate',
        ];

        // Stats that should be indented in the table
        $indentStats = [
            'submissionsDeclinedDeskReject',
            'submissionsDeclinedPostReview',
            'daysToAccept',
            'daysToReject',
            'declinedDeskRate',
            'declinedReviewRate',
        ];

        // Compile table rows
        $tableRows = [];
        foreach ($totals as $i => $stat) {
            $row = [
                'key' => $stat['key'],
                'name' => __($stat['name']),
                'total' => $stat['value'],
                'dateRange' => $dateRangeTotals[$i]['value'],
            ];
            if (in_array($stat['key'], $indentStats)) {
                $row['name'] = ' ' . $row['name'];
            }
            if (in_array($stat['key'], $percentageStats)) {
                $row['total'] = ($stat['value'] * 100) . '%';
                $row['dateRange'] = ($dateRangeTotals[$i]['value'] * 100) . '%';
            }
            $description = $this->_getStatDescription($stat['key']);
            if ($description) {
                $row['description'] = $description;
            }
            if (array_key_exists($stat['key'], $averages)
                    && $averages[$stat['key']] !== -1
                    && $row['total'] > 0) {
                $row['total'] = __('stats.countWithYearlyAverage', [
                    'count' => $stat['value'],
                    'average' => $averages[$stat['key']],
                ]);
            }
            $tableRows[] = $row;
        }

        // Get the worflow stage counts
        $activeByStage = [];
        foreach (Application::get()->getApplicationStages() as $stageId) {
            $activeByStage[] = [
                'name' => __(Application::get()->getWorkflowStageName($stageId)),
                'count' => Services::get('editorialStats')->countActiveByStages($stageId, $args),
                'color' => Application::get()->getWorkflowStageColor($stageId),
            ];
        }

        $statsComponent = new \PKP\components\PKPStatsEditorialPage(
            $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'stats/editorial'),
            [
                'activeByStage' => $activeByStage,
                'averagesApiUrl' => $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'stats/editorial/averages'),
                'dateStart' => $dateStart,
                'dateEnd' => $dateEnd,
                'dateRangeOptions' => [
                    [
                        'dateStart' => date('Y-m-d', strtotime('-91 days')),
                        'dateEnd' => $dateEnd,
                        'label' => __('stats.dateRange.last90Days'),
                    ],
                    [
                        'dateStart' => date('Y-m-d', strtotime(date('Y') . '-01-01')),
                        'dateEnd' => $dateEnd,
                        'label' => __('stats.dateRange.thisYear'),
                    ],
                    [
                        'dateStart' => date('Y-m-d', strtotime((date('Y') - 1) . '-01-01')),
                        'dateEnd' => date('Y-m-d', strtotime((date('Y') - 1) . '-12-31')),
                        'label' => __('stats.dateRange.lastYear'),
                    ],
                    [
                        'dateStart' => date('Y-m-d', strtotime((date('Y') - 2) . '-01-01')),
                        'dateEnd' => date('Y-m-d', strtotime((date('Y') - 1) . '-12-31')),
                        'label' => __('stats.dateRange.lastTwoYears'),
                    ],
                ],
                'percentageStats' => $percentageStats,
                'tableColumns' => [
                    [
                        'name' => 'name',
                        'label' => __('common.name'),
                        'value' => 'name',
                    ],
                    [
                        'name' => 'dateRange',
                        'label' => $dateStart . ' — ' . $dateEnd,
                        'value' => 'dateRange',
                    ],
                    [
                        'name' => 'total',
                        'label' => __('stats.total'),
                        'value' => 'total',
                    ],
                ],
                'tableRows' => $tableRows,
            ]
        );

        $templateMgr->setLocaleKeys([
            'stats.descriptionForStat',
            'stats.countWithYearlyAverage',
        ]);
        $templateMgr->setState($statsComponent->getConfig());
        $templateMgr->assign([
            'pageComponent' => 'StatsEditorialPage',
            'pageTitle' => __('stats.editorialActivity'),
        ]);

        $templateMgr->display('stats/editorial.tpl');
    }

    /**
     * Display published submissions statistics page
     *
     * @param PKPRequest $request
     * @param array $args
     */
    public function publications($args, $request)
    {
        $dispatcher = $request->getDispatcher();
        $context = $request->getContext();

        if (!$context) {
            $dispatcher->handle404();
        }

        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION);
        AppLocale::requireComponents(LOCALE_COMPONENT_APP_SUBMISSION);

        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);

        $dateStart = date('Y-m-d', strtotime('-31 days'));
        $dateEnd = date('Y-m-d', strtotime('yesterday'));
        $count = 30;

        $timeline = Services::get('publicationStats')->getTimeline(PKPStatisticsHelper::STATISTICS_DIMENSION_DAY, [
            'assocTypes' => Application::ASSOC_TYPE_SUBMISSION,
            'contextIds' => $context->getId(),
            'count' => $count,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
        ]);

        $statsComponent = new \PKP\components\PKPStatsPublicationPage(
            $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'stats/publications'),
            [
                'timeline' => $timeline,
                'timelineInterval' => PKPStatisticsHelper::STATISTICS_DIMENSION_DAY,
                'timelineType' => 'abstract',
                'tableColumns' => [
                    [
                        'name' => 'title',
                        'label' => __('common.title'),
                    ],
                    [
                        'name' => 'abstractViews',
                        'label' => __('submission.abstractViews'),
                        'value' => 'abstractViews',
                    ],
                    [
                        'name' => 'galleyViews',
                        'label' => __('stats.fileViews'),
                        'value' => 'galleyViews',
                    ],
                    [
                        'name' => 'pdf',
                        'label' => __('stats.pdf'),
                        'value' => 'pdfViews',
                    ],
                    [
                        'name' => 'html',
                        'label' => __('stats.html'),
                        'value' => 'htmlViews',
                    ],
                    [
                        'name' => 'other',
                        'label' => __('common.other'),
                        'value' => 'otherViews',
                    ],
                    [
                        'name' => 'total',
                        'label' => __('stats.total'),
                        'value' => 'total',
                        'orderBy' => 'total',
                        'initialOrderDirection' => true,
                    ],
                ],
                'count' => $count,
                'dateStart' => $dateStart,
                'dateEnd' => $dateEnd,
                'dateRangeOptions' => [
                    [
                        'dateStart' => $dateStart,
                        'dateEnd' => $dateEnd,
                        'label' => __('stats.dateRange.last30Days'),
                    ],
                    [
                        'dateStart' => date('Y-m-d', strtotime('-91 days')),
                        'dateEnd' => $dateEnd,
                        'label' => __('stats.dateRange.last90Days'),
                    ],
                    [
                        'dateStart' => date('Y-m-d', strtotime('-12 months')),
                        'dateEnd' => $dateEnd,
                        'label' => __('stats.dateRange.last12Months'),
                    ],
                    [
                        'dateStart' => '',
                        'dateEnd' => '',
                        'label' => __('stats.dateRange.allDates'),
                    ],
                ],
                'orderBy' => 'total',
                'orderDirection' => true,
            ]
        );

        $templateMgr->setState($statsComponent->getConfig());
        $templateMgr->assign([
            'pageComponent' => 'StatsPublicationsPage',
            'pageTitle' => __('stats.publicationStats'),
            'pageWidth' => TemplateManager::PAGE_WIDTH_WIDE,
        ]);

        $templateMgr->display('stats/publications.tpl');
    }

    /**
     * Display users stats
     *
     */
    public function users(array $args, Request $request): void
    {
        $dispatcher = $request->getDispatcher();
        $context = $request->getContext();

        if (!$context) {
            $dispatcher->handle404();
        }

        // The POST handler is here merely to serve a redirection URL to the Vue component
        if ($request->isPost()) {
            echo $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'users/report', null, null, $request->getUserVars());
            exit;
        }

        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);

        $context = $request->getContext();
        $selfUrl = $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'stats', 'users');
        $reportForm = new PKP\components\forms\statistics\users\ReportForm($selfUrl, $context);

        $templateMgr->setState([
            'components' => [
                'usersReportForm' => $reportForm->getConfig()
            ]
        ]);
        $templateMgr->assign([
            'pageTitle' => __('stats.userStatistics'),
            'pageComponent' => 'StatsUsersPage',
            'userStats' => array_map(
                function ($item) {
                    $item['name'] = __($item['name']);
                    return $item;
                },
                Repo::user()->getRolesOverview(Repo::user()->getCollector()->filterByContextIds(['contextId' => $context->getId()]))
            ),
        ]);
        $templateMgr->display('stats/users.tpl');
    }

    /**
     * Set up the basic template for reports.
     *
     * @param PKPRequest $request
     */
    public function setupTemplate($request)
    {
        parent::setupTemplate($request);
        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_APP_SUBMISSION);
    }

    /**
     * Route to other Reports operations
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function reports($args, $request)
    {
        $path = array_shift($args);
        switch ($path) {
            case '':
            case 'reports':
                $this->displayReports($args, $request);
                break;
            case 'report':
                $this->report($args, $request);
                break;
            case 'reportGenerator':
                $this->reportGenerator($args, $request);
                break;
            case 'generateReport':
                $this->generateReport($args, $request);
                break;
            default: assert(false);
        }
    }

    /**
     * Display report possibilities (report plugins)
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function displayReports($args, $request)
    {
        $dispatcher = $request->getDispatcher();
        $context = $request->getContext();

        if (!$context) {
            $dispatcher->handle404();
        }

        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);

        $reportPlugins = PluginRegistry::loadCategory('reports');
        $templateMgr->assign('reportPlugins', $reportPlugins);

        $templateMgr->assign([
            'pageTitle' => __('manager.statistics.reports'),
        ]);
        $templateMgr->display('stats/reports.tpl');
    }

    /**
     * Delegates to plugins operations
     * related to report generation.
     *
     * @param array $args
     * @param Request $request
     */
    public function report($args, $request)
    {
        $this->setupTemplate($request);

        $pluginName = $request->getUserVar('pluginName');
        $reportPlugins = PluginRegistry::loadCategory('reports');
        $file = 'debug.txt';
        $current = file_get_contents($file);
        $current .= print_r("++++ reportPlugins ++++\n", true);
        $current .= print_r($reportPlugins, true);
        file_put_contents($file, $current);

        if ($pluginName == '' || !isset($reportPlugins[$pluginName])) {
            $request->redirect(null, null, 'stats', 'reports');
        }

        $plugin = $reportPlugins[$pluginName];
        $plugin->display($args, $request);
    }

    /**
     * Display page to generate custom reports.
     *
     * @param array $args
     * @param Request $request
     */
    public function reportGenerator($args, $request)
    {
        $this->setupTemplate($request);

        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_EDITOR);

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'breadcrumbs' => [
                [
                    'id' => 'reports',
                    'name' => __('manager.statistics.reports'),
                    'url' => $request->getRouter()->url($request, null, 'stats', 'reports'),
                ],
                [
                    'id' => 'customReportGenerator',
                    'name' => __('manager.statistics.reports.customReportGenerator')
                ],
            ],
            'pageTitle' => __('manager.statistics.reports.customReportGenerator'),
        ]);
        $templateMgr->display('stats/reportGenerator.tpl');
    }

    /**
     * Generate statistics reports from passed
     * request arguments.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function generateReport($args, $request)
    {
        $this->setupTemplate($request);
        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION);

        $router = $request->getRouter();
        $context = $router->getContext($request);

        // Retrieve site-level report plugins.
        $reportPlugin = PluginRegistry::loadPlugin('reports', 'usageStats', $context->getId());
        if (!$reportPlugin) {
            $request->redirect(null, 'stats', 'reports');
        }

        $file = 'debug.txt';
        $current = file_get_contents($file);
        $current .= print_r("++++ reportPlugin ++++\n", true);
        $current .= print_r($reportPlugin, true);
        file_put_contents($file, $current);

        $columns = $request->getUserVar('columns');
        $filters = (array) json_decode($request->getUserVar('filters'));
        if (!$filters) {
            $filters = $request->getUserVar('filters');
        }

        $orderBy = $request->getUserVar('orderBy');
        if ($orderBy) {
            $orderBy = (array) json_decode($orderBy);
            if (!$orderBy) {
                $orderBy = $request->getUserVar('orderBy');
            }
        } else {
            $orderBy = [];
        }
        $file = 'debug.txt';
        $current = file_get_contents($file);
        $current .= print_r("++++ generate report get csv ++++\n", true);
        $current .= print_r("++++ filters ++++\n", true);
        $current .= print_r($filters, true);
        file_put_contents($file, $current);

        $reportPlugin->getCSV($request, $columns, $filters, $orderBy);
    }

    //
    // Protected methods.
    //
    /**
     * Get a description for stats that require one
     *
     * @param string $key
     */
    protected function _getStatDescription($key)
    {
        switch ($key) {
            case 'daysToDecision': return __('stats.description.daysToDecision');
            case 'acceptanceRate': return __('stats.description.acceptRejectRate');
            case 'declineRate': return __('stats.description.acceptRejectRate');
        }
        return '';
    }
}
