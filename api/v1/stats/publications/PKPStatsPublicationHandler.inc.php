<?php

/**
 * @file api/v1/stats/PKPStatsPublicationHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsPublicationHandler
 * @ingroup api_v1_stats
 *
 * @brief Handle API requests for publication statistics.
 *
 */

use APP\core\Application;
use APP\core\Services;
use APP\facades\Repo;
use APP\i18n\AppLocale;
use APP\submission\Submission;
use PKP\handler\APIHandler;
use PKP\plugins\HookRegistry;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;
use PKP\statistics\PKPStatisticsHelper;
use Sokil\IsoCodes\IsoCodesFactory;

abstract class PKPStatsPublicationHandler extends APIHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_handlerPath = 'stats/publications';
        $roles = [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];
        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'getMany'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/abstract',
                    'handler' => [$this, 'getManyAbstract'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/galley',
                    'handler' => [$this, 'getManyGalley'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}',
                    'handler' => [$this, 'get'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}/abstract',
                    'handler' => [$this, 'getAbstract'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{submissionId:\d+}/galley',
                    'handler' => [$this, 'getGalley'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/files',
                    'handler' => [$this, 'getManyFiles'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/countries',
                    'handler' => [$this, 'getManyCountries'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/regions',
                    'handler' => [$this, 'getManyRegions'],
                    'roles' => $roles
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/cities',
                    'handler' => [$this, 'getManyCities'],
                    'roles' => $roles
                ],
            ],
        ];
        parent::__construct();
    }

    //
    // Implement methods from PKPHandler
    //
    public function authorize($request, &$args, $roleAssignments)
    {
        $routeName = null;
        $slimRequest = $this->getSlimRequest();

        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);
        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        if (!is_null($slimRequest) && ($route = $slimRequest->getAttribute('route'))) {
            $routeName = $route->getName();
        }
        if (in_array($routeName, ['get', 'getAbstract', 'getGalley'])) {
            $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get usage stats for a set of publications
     *
     * Returns total views by abstract, pdf galleys,
     * html galleys, and other galleys.
     */
    public function getMany(\Slim\Http\Request $slimRequest, \PKP\core\APIResponse $response, array $args): \PKP\core\APIResponse
    {
        $responseCSV = str_contains($slimRequest->getHeaderLine('Accept'), \PKP\core\APIResponse::RESPONSE_CSV) ? true : false;

        $defaultParams = [
            'count' => 30,
            'offset' => 0,
            'orderDirection' => PKPStatisticsHelper::STATISTICS_ORDER_DESC,
        ];
        $initAllowedParams = [
            'dateStart',
            'dateEnd',
            'count',
            'offset',
            'orderDirection',
            'searchPhrase',
            $this->sectionIdsQueryParam,
            'submissionIds',
        ];

        // Check/validate, filter and sanitize the request params
        try {
            $allowedParams = $this->_checkDefaultParams($slimRequest, $defaultParams, $initAllowedParams);
        } catch (\Exception $e) {
            if ($e->getCode() == 200) {
                $csvColumnNames = $this->_getSubmissionReportColumnNames();
                if ($responseCSV) {
                    return $response->withCSV(0, [], $csvColumnNames);
                } else {
                    return $response->withJson([
                        'items' => [],
                        'itemsMax' => 0,
                    ], 200);
                }
            } else {
                $response->withStatus($e->getCode())->withJsonError($e->getMessage());
            }
        }

        $statsService = Services::get('publicationStats');
        // Get a list of top publications by total views
        $totalMetrics = $statsService->getTotalMetrics($allowedParams);

        // Get the stats for each publication
        $items = [];
        foreach ($totalMetrics as $totalMetric) {
            if (empty($totalMetric->submission_id)) {
                continue;
            }
            $submissionId = $totalMetric->submission_id;

            // get abstract, pdf, html and other views for the submission
            $typeParams = $allowedParams;
            $typeParams['submissionIds'] = $submissionId;
            $metricsByType = $statsService->getMetricsByType($typeParams);

            $abstractViews = $pdfViews = $htmlViews = $otherViews = $totalViews = 0;
            $abstractRecord = array_filter($metricsByType, [$statsService, 'filterRecordAbstract']);
            if (!empty($abstractRecord)) {
                $abstractViews = (int) current($abstractRecord)->metric;
            }
            $pdfRecord = array_filter($metricsByType, [$statsService, 'filterRecordPdf']);
            if (!empty($pdfRecord)) {
                $pdfViews = (int) current($pdfRecord)->metric;
            }
            $htmlRecord = array_filter($metricsByType, [$statsService, 'filterRecordHtml']);
            if (!empty($htmlRecord)) {
                $htmlViews = (int) current($htmlRecord)->metric;
            }
            $otherRecord = array_filter($metricsByType, [$statsService, 'filterRecordOther']);
            if (!empty($otherRecord)) {
                $otherViews = (int) current($otherRecord)->metric;
            }

            if ($responseCSV) {
                $items[] = $this->getCSVItem($submissionId, $abstractViews, $pdfViews, $htmlViews, $otherViews);
            } else {
                $items[] = $this->getJSONItem($submissionId, $abstractViews, $pdfViews, $htmlViews, $otherViews);
            }
        }

        // Get the total count of submissions
        $itemsMax = $statsService->getTotalCount($allowedParams);
        $csvColumnNames = $this->_getSubmissionReportColumnNames();
        if ($responseCSV) {
            return $response->withCSV($itemsMax, $items, $csvColumnNames);
        } else {
            return $response->withJson([
                'items' => $items,
                'itemsMax' => $itemsMax,
            ], 200);
        }
    }

    /**
     * Get the total abstract views for a set of publications
     * in a timeline broken down month or day
     */
    public function getManyAbstract(\Slim\Http\Request $slimRequest, \PKP\core\APIResponse $response, array $args): \PKP\core\APIResponse
    {
        $defaultParams = [
            'timelineInterval' => PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH,
        ];
        $initAllowedParams = [
            'dateStart',
            'dateEnd',
            'timelineInterval',
            'searchPhrase',
            $this->sectionIdsQueryParam,
            'submissionIds',
        ];

        $statsService = Services::get('publicationStats');

        // Check/validate, filter and sanitize the request params
        try {
            $allowedParams = $this->_checkDefaultParams($slimRequest, $defaultParams, $initAllowedParams);
        } catch (\Exception $e) {
            if ($e->getCode() == 200) {
                $dateStart = empty($allowedParams['dateStart']) ? PKPStatisticsHelper::STATISTICS_EARLIEST_DATE : $allowedParams['dateStart'];
                $dateEnd = empty($allowedParams['dateEnd']) ? date('Ymd', strtotime('yesterday')) : $allowedParams['dateEnd'];
                $emptyTimeline = $statsService->getEmptyTimelineIntervals($dateStart, $dateEnd, $allowedParams['timelineInterval']);
                return $response->withJson($emptyTimeline, 200);
            } else {
                $response->withStatus($e->getCode())->withJsonError($e->getMessage());
            }
        }

        $allowedParams['assocTypes'] = Application::ASSOC_TYPE_SUBMISSION;
        $data = $statsService->getTimeline($allowedParams['timelineInterval'], $allowedParams);
        return $response->withJson($data, 200);
    }

    /**
     * Get the total galley views for a set of publications
     * in a timeline broken down month or day
     */
    public function getManyGalley(\Slim\Http\Request $slimRequest, \PKP\core\APIResponse $response, array $args): \PKP\core\APIResponse
    {
        $defaultParams = [
            'timelineInterval' => PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH,
        ];
        $initAllowedParams = [
            'dateStart',
            'dateEnd',
            'timelineInterval',
            'searchPhrase',
            $this->sectionIdsQueryParam,
            'submissionIds',
        ];

        $statsService = Services::get('publicationStats');

        // Check/validate, filter and sanitize the request params
        try {
            $allowedParams = $this->_checkDefaultParams($slimRequest, $defaultParams, $initAllowedParams);
        } catch (\Exception $e) {
            if ($e->getCode() == 200) {
                $dateStart = empty($allowedParams['dateStart']) ? PKPStatisticsHelper::STATISTICS_EARLIEST_DATE : $allowedParams['dateStart'];
                $dateEnd = empty($allowedParams['dateEnd']) ? date('Ymd', strtotime('yesterday')) : $allowedParams['dateEnd'];
                $emptyTimeline = $statsService->getEmptyTimelineIntervals($dateStart, $dateEnd, $allowedParams['timelineInterval']);
                return $response->withJson($emptyTimeline, 200);
            } else {
                $response->withStatus($e->getCode())->withJsonError($e->getMessage());
            }
        }

        $allowedParams['assocTypes'] = Application::ASSOC_TYPE_SUBMISSION_FILE;
        $data = $statsService->getTimeline($allowedParams['timelineInterval'], $allowedParams);
        return $response->withJson($data, 200);
    }

    /**
     * Get a single publication's usage statistics
     */
    public function get(\Slim\Http\Request $slimRequest, \PKP\core\APIResponse $response, array $args): \PKP\core\APIResponse
    {
        $request = $this->getRequest();

        if (!$request->getContext()) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        if (!$submission) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $allowedParams = $this->_processAllowedParams($slimRequest->getQueryParams(), [
            'dateStart',
            'dateEnd',
        ]);

        HookRegistry::call('API::stats::publication::params', [&$allowedParams, $slimRequest]);

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            return $response->withStatus(400)->withJsonError($result);
        }

        $allowedParams['submissionIds'] = [$submission->getId()];
        $allowedParams['contextIds'] = $request->getContext()->getId();

        $statsService = Services::get('publicationStats');
        $metricsByType = $statsService->getMetricsByType($allowedParams);

        $abstractViews = $pdfViews = $htmlViews = $otherViews = 0;
        $abstractRecord = array_filter($metricsByType, [$statsService, 'filterRecordAbstract']);
        if (!empty($abstractRecord)) {
            $abstractViews = (int) current($abstractRecord)->metric;
        }
        $pdfRecord = array_filter($metricsByType, [$statsService, 'filterRecordPdf']);
        if (!empty($pdfRecord)) {
            $pdfViews = (int) current($pdfRecord)->metric;
        }
        $htmlRecord = array_filter($metricsByType, [$statsService, 'filterRecordHtml']);
        if (!empty($htmlRecord)) {
            $htmlViews = (int) current($htmlRecord)->metric;
        }
        $otherRecord = array_filter($metricsByType, [$statsService, 'filterRecordOther']);
        if (!empty($otherRecord)) {
            $otherViews = (int) current($otherRecord)->metric;
        }
        $galleyViews = $pdfViews + $htmlViews + $otherViews;

        return $response->withJson([
            'abstractViews' => $abstractViews,
            'galleyViews' => $galleyViews,
            'pdfViews' => $pdfViews,
            'htmlViews' => $htmlViews,
            'otherViews' => $otherViews,
            'publication' => Repo::submission()->getSchemaMap()->mapToStats($submission),
        ], 200);
    }

    /**
     * Get the total abstract views for a set of publications broken down by
     * month or day
     */
    public function getAbstract(\Slim\Http\Request $slimRequest, \PKP\core\APIResponse $response, array $args): \PKP\core\APIResponse
    {
        $request = $this->getRequest();

        if (!$request->getContext()) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        if (!$submission) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $defaultParams = [
            'timelineInterval' => PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH,
        ];

        $requestParams = array_merge($defaultParams, $slimRequest->getQueryParams());

        $allowedParams = $this->_processAllowedParams($requestParams, [
            'dateStart',
            'dateEnd',
            'timelineInterval',
        ]);

        $allowedParams['contextIds'] = $request->getContext()->getId();
        $allowedParams['submissionIds'] = $submission->getId();
        $allowedParams['assocTypes'] = Application::ASSOC_TYPE_SUBMISSION;

        HookRegistry::call('API::stats::publication::abstract::params', [&$allowedParams, $slimRequest]);

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            return $response->withStatus(400)->withJsonError($result);
        }

        $statsService = Services::get('publicationStats');
        $data = $statsService->getTimeline($allowedParams['timelineInterval'], $allowedParams);

        return $response->withJson($data, 200);
    }

    /**
     * Get the total galley views for a publication broken down by
     * month or day
     */
    public function getGalley(\Slim\Http\Request $slimRequest, \PKP\core\APIResponse $response, array $args): \PKP\core\APIResponse
    {
        $request = $this->getRequest();

        if (!$request->getContext()) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        if (!$submission) {
            return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
        }

        $defaultParams = [
            'timelineInterval' => PKPStatisticsHelper::STATISTICS_DIMENSION_MONTH,
        ];

        $requestParams = array_merge($defaultParams, $slimRequest->getQueryParams());

        $allowedParams = $this->_processAllowedParams($requestParams, [
            'dateStart',
            'dateEnd',
            'timelineInterval',
        ]);

        $allowedParams['contextIds'] = $request->getContext()->getId();
        $allowedParams['submissionIds'] = $submission->getId();
        $allowedParams['assocTypes'] = Application::ASSOC_TYPE_SUBMISSION_FILE;

        HookRegistry::call('API::stats::publication::galley::params', [&$allowedParams, $slimRequest]);

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            return $response->withStatus(400)->withJsonError($result);
        }

        $statsService = Services::get('publicationStats');
        $data = $statsService->getTimeline($allowedParams['timelineInterval'], $allowedParams);

        return $response->withJson($data, 200);
    }

    /**
     * Get total usage stats for a set of submission files in CSV format, ordered DESC by count.
     */
    public function getManyFiles(\Slim\Http\Request $slimRequest, \PKP\core\APIResponse $response, array $args): \PKP\core\APIResponse
    {
        $responseCSV = str_contains($slimRequest->getHeaderLine('Accept'), \PKP\core\APIResponse::RESPONSE_CSV) ? true : false;

        $defaultParams = [
            'count' => 30,
            'offset' => 0,
            'orderDirection' => PKPStatisticsHelper::STATISTICS_ORDER_DESC,
        ];
        $initAllowedParams = [
            'dateStart',
            'dateEnd',
            'count',
            'offset',
            'orderDirection',
            'searchPhrase',
            $this->sectionIdsQueryParam,
            'submissionIds',
        ];

        // Check/validate, filter and sanitize the request params
        try {
            $allowedParams = $this->_checkDefaultParams($slimRequest, $defaultParams, $initAllowedParams);
        } catch (\Exception $e) {
            if ($e->getCode() == 200) {
                $csvColumnNames = $this->_getFileReportColumnNames();
                if ($responseCSV) {
                    return $response->withCSV(0, [], $csvColumnNames);
                } else {
                    return $response->withJson([
                        'items' => [],
                        'itemsMax' => 0,
                    ], 200);
                }
            } else {
                $response->withStatus($e->getCode())->withJsonError($e->getMessage());
            }
        }

        $statsService = Services::get('publicationStats');
        $filesMetrics = $statsService->getFilesMetrics($allowedParams);

        $items = $submissionTitles = [];
        foreach ($filesMetrics as $fileMetric) {
            if (empty($fileMetric->submission_id)) {
                continue;
            }
            $submissionId = $fileMetric->submission_id;
            $fileId = $fileMetric->file_id;
            $downloads = $fileMetric->metric;

            if (!isset($submissionTitles[$submissionId])) {
                // Stats may exist for deleted submissions
                $submissionTitle = '';
                $submission = Repo::submission()->get($submissionId);
                if ($submission) {
                    $submissionTitle = $submission->getLocalizedTitle();
                }
                $submissionTitles[$submissionId] = $submissionTitle;
            }

            if ($responseCSV) {
                $items[] = $this->getFilesCSVItem($fileId, $downloads, $submissionTitles[$submissionId]);
            } else {
                $items[] = $this->getFilesJSONItem($fileId, $downloads, $submissionTitles[$submissionId]);
            }
        }

        // Get the total count of submissions
        $itemsMax = $statsService->getTotalFilesCountetMetrics($allowedParams);
        $csvColumnNames = $this->_getFileReportColumnNames();
        if ($responseCSV) {
            return $response->withCSV($itemsMax, $items, $csvColumnNames);
        } else {
            return $response->withJson([
                'items' => $items,
                'itemsMax' => $itemsMax,
            ], 200);
        }
    }

    /**
     * Get countries usage stats
     *
     * Returns total counts (of all submissions within filter range) for views, downloads, unique views and unique downloads.
     */
    public function getManyCountries(\Slim\Http\Request $slimRequest, \PKP\core\APIResponse $response, array $args): \PKP\core\APIResponse
    {
        $responseCSV = str_contains($slimRequest->getHeaderLine('Accept'), \PKP\core\APIResponse::RESPONSE_CSV) ? true : false;

        $defaultParams = [
            'count' => 30,
            'offset' => 0,
            'orderDirection' => PKPStatisticsHelper::STATISTICS_ORDER_DESC,
        ];
        $initAllowedParams = [
            'dateStart',
            'dateEnd',
            'count',
            'offset',
            'orderDirection',
            'searchPhrase',
            $this->sectionIdsQueryParam,
            'submissionIds',
        ];

        // Check/validate, filter and sanitize the request params
        try {
            $allowedParams = $this->_checkDefaultParams($slimRequest, $defaultParams, $initAllowedParams);
        } catch (\Exception $e) {
            if ($e->getCode() == 200) {
                $csvColumnNames = $this->_getGeoReportColumnNames(PKPStatisticsHelper::STATISTICS_DIMENSION_COUNTRY);
                if ($responseCSV) {
                    return $response->withCSV(0, [], $csvColumnNames);
                } else {
                    return $response->withJson([
                        'items' => [],
                        'itemsMax' => 0,
                    ], 200);
                }
            } else {
                $response->withStatus($e->getCode())->withJsonError($e->getMessage());
            }
        }

        $statsService = Services::get('geoStats');
        // Get a list of top countries by total views
        $totals = $statsService->getTotalMetrics($allowedParams, PKPStatisticsHelper::STATISTICS_DIMENSION_COUNTRY);

        // Get the stats for each country
        $items = [];
        $isoCodes = app(IsoCodesFactory::class);
        foreach ($totals as $total) {
            if (empty($total->country)) {
                continue;
            }
            $countryName = __('stats.unknown');
            $country = $isoCodes->getCountries()->getByAlpha2($total->country);
            if ($country) {
                $countryName = $country->getLocalName();
            }
            $totalViews = $total->metric_investigations;
            $totalDownloads = $total->metric_requests;
            $uniqueViews = $total->metric_investigations_unique;
            $uniqueDownloads = $total->metric_requests_unique;

            if ($responseCSV) {
                $items[] = $this->getGeoCSVItem($totalViews, $totalDownloads, $uniqueViews, $uniqueDownloads, $countryName);
            } else {
                $items[] = $this->getGeoJSONItem($totalViews, $totalDownloads, $uniqueViews, $uniqueDownloads, $countryName);
            }
        }

        // Get the total count of submissions
        $itemsMax = $statsService->getTotalCount($allowedParams, PKPStatisticsHelper::STATISTICS_DIMENSION_COUNTRY);
        $csvColumnNames = $this->_getGeoReportColumnNames(PKPStatisticsHelper::STATISTICS_DIMENSION_COUNTRY);
        if ($responseCSV) {
            return $response->withCSV($itemsMax, $items, $csvColumnNames);
        } else {
            return $response->withJson([
                'items' => $items,
                'itemsMax' => $itemsMax,
            ], 200);
        }
    }

    /**
     * Get regions usage stats
     *
     * Returns total counts (of all submissions within filter range) for views, downloads, unique views and unique downloads.
     */
    public function getManyRegions(\Slim\Http\Request $slimRequest, \PKP\core\APIResponse $response, array $args): \PKP\core\APIResponse
    {
        $responseCSV = str_contains($slimRequest->getHeaderLine('Accept'), \PKP\core\APIResponse::RESPONSE_CSV) ? true : false;

        $defaultParams = [
            'count' => 30,
            'offset' => 0,
            'orderDirection' => PKPStatisticsHelper::STATISTICS_ORDER_DESC,
        ];
        $initAllowedParams = [
            'dateStart',
            'dateEnd',
            'count',
            'offset',
            'orderDirection',
            'searchPhrase',
            $this->sectionIdsQueryParam,
            'submissionIds',
        ];

        // Check/validate, filter and sanitize the request params
        try {
            $allowedParams = $this->_checkDefaultParams($slimRequest, $defaultParams, $initAllowedParams);
        } catch (\Exception $e) {
            if ($e->getCode() == 200) {
                $csvColumnNames = $this->_getGeoReportColumnNames(PKPStatisticsHelper::STATISTICS_DIMENSION_REGION);
                if ($responseCSV) {
                    return $response->withCSV(0, [], $csvColumnNames);
                } else {
                    return $response->withJson([
                        'items' => [],
                        'itemsMax' => 0,
                    ], 200);
                }
            } else {
                $response->withStatus($e->getCode())->withJsonError($e->getMessage());
            }
        }

        $statsService = Services::get('geoStats');
        // Get a list of top regions by total views
        $totals = $statsService->getTotalMetrics($allowedParams, PKPStatisticsHelper::STATISTICS_DIMENSION_REGION);

        // Get the stats for each region
        $items = [];
        $isoCodes = app(IsoCodesFactory::class);
        foreach ($totals as $total) {
            if (empty($total->country)) {
                continue;
            }
            $countryName = __('stats.unknown');
            $country = $isoCodes->getCountries()->getByAlpha2($total->country);
            if ($country) {
                $countryName = $country->getLocalName();
            }
            $regionName = __('stats.unknown');
            if (!empty($total->region)) {
                $regionCode = $total->country . '-' . $total->region;
                $region = $isoCodes->getSubdivisions()->getByCode($regionCode);
                if ($region) {
                    $regionName = $region->getLocalName();
                }
            }
            $totalViews = $total->metric_investigations;
            $totalDownloads = $total->metric_requests;
            $uniqueViews = $total->metric_investigations_unique;
            $uniqueDownloads = $total->metric_requests_unique;

            if ($responseCSV) {
                $items[] = $this->getGeoCSVItem($totalViews, $totalDownloads, $uniqueViews, $uniqueDownloads, $countryName, $regionName);
            } else {
                $items[] = $this->getGeoJSONItem($totalViews, $totalDownloads, $uniqueViews, $uniqueDownloads, $countryName, $regionName);
            }
        }

        // Get the total count of submissions
        $itemsMax = $statsService->getTotalCount($allowedParams, PKPStatisticsHelper::STATISTICS_DIMENSION_REGION);
        $csvColumnNames = $this->_getGeoReportColumnNames(PKPStatisticsHelper::STATISTICS_DIMENSION_REGION);
        if ($responseCSV) {
            return $response->withCSV($itemsMax, $items, $csvColumnNames);
        } else {
            return $response->withJson([
                'items' => $items,
                'itemsMax' => $itemsMax,
            ], 200);
        }
    }

    /**
     * Get cities usage stats
     *
     * Returns total counts (of all submissions within filter range) for views, downloads, unique views and unique downloads.
     */
    public function getManyCities(\Slim\Http\Request $slimRequest, \PKP\core\APIResponse $response, array $args): \PKP\core\APIResponse
    {
        $responseCSV = str_contains($slimRequest->getHeaderLine('Accept'), \PKP\core\APIResponse::RESPONSE_CSV) ? true : false;

        $defaultParams = [
            'count' => 30,
            'offset' => 0,
            'orderDirection' => PKPStatisticsHelper::STATISTICS_ORDER_DESC,
        ];
        $initAllowedParams = [
            'dateStart',
            'dateEnd',
            'count',
            'offset',
            'orderDirection',
            'searchPhrase',
            $this->sectionIdsQueryParam,
            'submissionIds',
        ];

        // Check/validate, filter and sanitize the request params
        try {
            $allowedParams = $this->_checkDefaultParams($slimRequest, $defaultParams, $initAllowedParams);
        } catch (\Exception $e) {
            if ($e->getCode() == 200) {
                $csvColumnNames = $this->_getGeoReportColumnNames(PKPStatisticsHelper::STATISTICS_DIMENSION_CITY);
                if ($responseCSV) {
                    return $response->withCSV(0, [], $csvColumnNames);
                } else {
                    return $response->withJson([
                        'items' => [],
                        'itemsMax' => 0,
                    ], 200);
                }
            } else {
                $response->withStatus($e->getCode())->withJsonError($e->getMessage());
            }
        }

        $statsService = Services::get('geoStats');
        // Get a list of top cities by total views
        $totals = $statsService->getTotalMetrics($allowedParams, PKPStatisticsHelper::STATISTICS_DIMENSION_CITY);

        // Get the stats for each city
        $items = [];
        $isoCodes = app(IsoCodesFactory::class);
        foreach ($totals as $total) {
            if (empty($total->country)) {
                continue;
            }
            $countryName = __('stats.unknown');
            $country = $isoCodes->getCountries()->getByAlpha2($total->country);
            if ($country) {
                $countryName = $country->getLocalName();
            }
            $regionName = __('stats.unknown');
            if (!empty($total->region)) {
                $regionCode = $total->country . '-' . $total->region;
                $region = $isoCodes->getSubdivisions()->getByCode($regionCode);
                if ($region) {
                    $regionName = $region->getLocalName();
                }
            }
            $cityName = __('stats.unknown');
            if (!empty($total->city)) {
                $cityName = $total->city;
            }

            $totalViews = $total->metric_investigations;
            $totalDownloads = $total->metric_requests;
            $uniqueViews = $total->metric_investigations_unique;
            $uniqueDownloads = $total->metric_requests_unique;

            if ($responseCSV) {
                $items[] = $this->getGeoCSVItem($totalViews, $totalDownloads, $uniqueViews, $uniqueDownloads, $countryName, $regionName, $cityName);
            } else {
                $items[] = $this->getGeoJSONItem($totalViews, $totalDownloads, $uniqueViews, $uniqueDownloads, $countryName, $regionName, $cityName);
            }
        }

        // Get the total count of submissions
        $itemsMax = $statsService->getTotalCount($allowedParams, PKPStatisticsHelper::STATISTICS_DIMENSION_CITY);
        $csvColumnNames = $this->_getGeoReportColumnNames(PKPStatisticsHelper::STATISTICS_DIMENSION_CITY);
        if ($responseCSV) {
            return $response->withCSV($itemsMax, $items, $csvColumnNames);
        } else {
            return $response->withJson([
                'items' => $items,
                'itemsMax' => $itemsMax,
            ], 200);
        }
    }

    /**
     * Get the correct hook name, depending on the API endpoint called
     */
    private function _getParamsHookName(string $path): string
    {
        switch (true) {
            case str_ends_with($path, 'publications'):
                return 'API::stats::publications::params';
            case str_ends_with($path, 'abstract'):
                return 'API::stats::publications::abstract::params';
            case str_ends_with($path, 'galley'):
                return 'API::stats::publications::galley::params';
            case str_ends_with($path, 'files'):
                return 'API::stats::publications::files::params';
            case str_ends_with($path, 'countries'):
                return 'API::stats::publications::countries::params';
            case str_ends_with($path, 'regions'):
                return 'API::stats::publications::regions::params';
            case str_ends_with($path, 'cities'):
                return 'API::stats::publications::cities::params';
        }
    }

    /**
     * Validate, filter, sanitize the requests params
     */
    private function _checkDefaultParams(\Slim\Http\Request $slimRequest, array $defaultParams, array $initAllowedParams): array
    {
        $request = $this->getRequest();

        if (!$request->getContext()) {
            throw new \Exception('api.404.resourceNotFound', 404);
        }

        $requestParams = array_merge($defaultParams, $slimRequest->getQueryParams());

        $allowedParams = $this->_processAllowedParams($requestParams, $initAllowedParams);

        $allowedParams['contextIds'] = $request->getContext()->getId();

        $hookName = $this->_getParamsHookName($slimRequest->getUri()->getPath());
        HookRegistry::call($hookName, [&$allowedParams, $slimRequest]);

        $result = $this->_validateStatDates($allowedParams);
        if ($result !== true) {
            throw new \Exception($result, 400);
        }

        if (!in_array($allowedParams['orderDirection'], [PKPStatisticsHelper::STATISTICS_ORDER_ASC, PKPStatisticsHelper::STATISTICS_ORDER_DESC])) {
            throw new \Exception('api.stats.400.invalidOrderDirection', 400);
        }

        // Identify submissions which should be included in the results when a searchPhrase is passed
        if (!empty($allowedParams['searchPhrase'])) {
            $allowedSubmissionIds = empty($allowedParams['submissionIds']) ? [] : $allowedParams['submissionIds'];
            $allowedParams['submissionIds'] = $this->_processSearchPhrase($allowedParams['searchPhrase'], $allowedSubmissionIds);

            if (empty($allowedParams['submissionIds'])) {
                throw new \Exception('', 200);
            }
        }

        $statsService = Services::get('publicationStats');
        // Identify submissions which should be included in the results when a section i.e. series Id is passed
        if (isset($allowedParams[$this->sectionIdsQueryParam])) {
            $allowedSubmissionIds = empty($allowedParams['submissionIds']) ? [] : $allowedParams['submissionIds'];
            $allowedParams['submissionIds'] = $statsService->processSectionIds($allowedParams[$this->sectionIdsQueryParam], $allowedSubmissionIds);

            if (empty($allowedParams['submissionIds'])) {
                throw new \Exception('', 200);
            }
        }
        return $allowedParams;
    }

    /**
     * A helper method to filter and sanitize the request params
     *
     * Only allows the specified params through and enforces variable
     * type where needed.
     */
    protected function _processAllowedParams(array $requestParams, array $allowedParams): array
    {
        $returnParams = [];
        foreach ($requestParams as $requestParam => $value) {
            if (!in_array($requestParam, $allowedParams)) {
                continue;
            }
            switch ($requestParam) {
                case 'dateStart':
                case 'dateEnd':
                case 'timelineInterval':
                case 'searchPhrase':
                    $returnParams[$requestParam] = $value;
                    break;

                case 'count':
                    $returnParams[$requestParam] = min(100, (int) $value);
                    break;

                case 'offset':
                    $returnParams[$requestParam] = (int) $value;
                    break;

                case 'orderDirection':
                    $returnParams[$requestParam] = strtoupper($value);
                    break;

                case $this->sectionIdsQueryParam:
                case 'submissionIds':
                    if (is_string($value) && strpos($value, ',') > -1) {
                        $value = explode(',', $value);
                    } elseif (!is_array($value)) {
                        $value = [$value];
                    }
                    $returnParams[$requestParam] = array_map('intval', $value);
                    break;
                case 'countries':
                    if (is_string($value) && strpos($value, ',') > -1) {
                        $value = explode(',', $value);
                    } elseif (!is_array($value)) {
                        $value = [$value];
                    }
                    $returnParams[$requestParam] = $value;
            }
        }

        /*
        // Get the earliest date of publication if no start date set
        if (in_array('dateStart', $allowedParams) && !isset($returnParams['dateStart'])) {
            $dateRange = Repo::publication()->getDateBoundaries(
                Repo::publication()
                    ->getCollector()
                    ->filterByContextIds([$this->getRequest()->getContext()->getId()])
            );
            $returnParams['dateStart'] = $dateRange->min_date_published;
        }
        */

        return $returnParams;
    }

    /**
     * A helper method to get the submissionIds param when a searchPhase
     * param is also passed.
     *
     * If the searchPhrase and submissionIds params were both passed in the
     * request, then we only return ids that match both conditions.
     */
    protected function _processSearchPhrase(string $searchPhrase, array $submissionIds = []): array
    {
        $searchPhraseSubmissionIds = Repo::submission()->getIds(
            Repo::submission()
                ->getCollector()
                ->filterByContextIds([Application::get()->getRequest()->getContext()->getId()])
                ->filterByStatus([Submission::STATUS_PUBLISHED])
                ->searchPhrase($searchPhrase)
        );

        if (!empty($submissionIds)) {
            $submissionIds = array_intersect($submissionIds, $searchPhraseSubmissionIds->toArray());
        } else {
            $submissionIds = $searchPhraseSubmissionIds->toArray();
        }

        return $submissionIds;
    }

    /**
     * Get column names for the submisison CSV report
     */
    protected function _getSubmissionReportColumnNames(): array
    {
        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION);
        return [
            __('common.id'),
            __('common.title'),
            __('stats.total'),
            __('submission.abstractViews'),
            __('stats.fileViews'),
            __('stats.pdf'),
            __('stats.html'),
            __('common.other'),
        ];
    }

    /**
     * Get column names for the file CSV report
     */
    protected function _getFileReportColumnNames(): array
    {
        return [
            __('common.id'),
            __('common.title'),
            __('stats.fileViews'),
            __('common.publication'),
        ];
    }

    /**
     * Get column names for the country, region and city CSV report
     */
    protected function _getGeoReportColumnNames(string $scale, bool $withPublication = false): array
    {
        $publicationColumns = [];
        if ($withPublication) {
            $publicationColumns = [
                __('common.id'),
                __('common.title')
            ];
        }

        $scaleColumns = [];
        if ($scale == PKPStatisticsHelper::STATISTICS_DIMENSION_CITY) {
            $scaleColumns = [
                __('stats.city'),
                __('stats.region')
            ];
        } elseif ($scale == PKPStatisticsHelper::STATISTICS_DIMENSION_REGION) {
            $scaleColumns = [__('stats.region')];
        } elseif ($scale == PKPStatisticsHelper::STATISTICS_DIMENSION_COUNTRY) {
            $scaleColumns = [__('common.country'),];
        }

        return array_merge(
            $publicationColumns,
            $scaleColumns,
            [__('stats.totalViews'),
                __('stats.totalDownloads'),
                __('stats.uniqueViews'),
                __('stats.uniqueDownloads')]
        );
    }

    protected function getCSVItem(int $submissionId, int $abstractViews, int $pdfViews, int $htmlViews, int $otherViews): array
    {
        $galleyViews = $pdfViews + $htmlViews + $otherViews;
        $totalViews = $abstractViews + $galleyViews;

        // Get submission title for display
        // Stats may exist for deleted submissions
        $submissionTitle = '';
        $submission = Repo::submission()->get($submissionId);
        if ($submission) {
            $submissionTitle = $submission->getLocalizedTitle();
        }

        return [
            $submissionId,
            $submissionTitle,
            $totalViews,
            $abstractViews,
            $galleyViews,
            $pdfViews,
            $htmlViews,
            $otherViews
        ];
    }

    protected function getJSONItem(int $submissionId, int $abstractViews, int $pdfViews, int $htmlViews, int $otherViews): array
    {
        $galleyViews = $pdfViews + $htmlViews + $otherViews;

        // Get basic submission details for display
        // Stats may exist for deleted submissions
        $submissionProps = ['id' => $submissionId];
        $submission = Repo::submission()->get($submissionId);
        if ($submission) {
            $submissionProps = Repo::submission()->getSchemaMap()->mapToStats($submission);
        }

        return [
            'abstractViews' => $abstractViews,
            'galleyViews' => $galleyViews,
            'pdfViews' => $pdfViews,
            'htmlViews' => $htmlViews,
            'otherViews' => $otherViews,
            'publication' => $submissionProps,
        ];
    }

    protected function getFilesCSVItem(int $fileId, int $downloads, string $submissionTitle): array
    {
        // Stats may exist for deleted files
        $title = '';
        $submissionFile = Services::get('submissionFile')->get($fileId);
        if ($submissionFile) {
            $title = $submissionFile->getLocalizedData('name');
        }
        return [
            $fileId,
            $title,
            $downloads,
            $submissionTitle
        ];
    }

    protected function getFilesJSONItem(int $fileId, int $downloads, int $submissionTitle): array
    {
        // Stats may exist for deleted files
        $title = '';
        $submissionFile = Services::get('submissionFile')->get($fileId);
        if ($submissionFile) {
            $title = $submissionFile->getLocalizedData('name');
        }
        return [
            'fileId' => $fileId,
            'fileName' => $title,
            'downloads' => $downloads,
            'submissionTitle' => $submissionTitle
        ];
    }

    protected function getGeoCSVItem(int $totalViews, int $totalDownloads, int $uniqueViews, int $uniqueDownloads, string $country, ?string $region = null, ?string $city = null): array
    {
        $item = [];
        if (isset($city)) {
            $item[] = $city;
        }
        if (isset($region)) {
            $item[] = $region;
        }
        return array_merge($item, [
            $country,
            $totalViews,
            $totalDownloads,
            $uniqueViews,
            $uniqueDownloads
        ]);
    }

    protected function getGeoJSONItem(int $totalViews, int $totalDownloads, int $uniqueViews, int $uniqueDownloads, string $country, ?string $region = null, ?string $city = null): array
    {
        $item = [];
        if (isset($city)) {
            $item['city'] = $city;
        }
        if (isset($region)) {
            $item['region'] = $region;
        }
        return array_merge($item, [
            'country' => $country,
            'totalViews' => $totalViews,
            'totalDownloads' => $totalDownloads,
            'uniqueViews' => $uniqueViews,
            'uniqueDownloads' => $uniqueDownloads
        ]);
    }
}
