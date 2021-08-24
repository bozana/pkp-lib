<?php

/**
 * @file classes/services/PKPStatsGeoService.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsGeoService
 * @ingroup services
 *
 * @brief Helper class that encapsulates geographic statistics business logic
 */

namespace PKP\services;

use APP\statistics\StatisticsHelper;
use PKP\plugins\HookRegistry;

class PKPStatsGeoService extends PKPStatsService
{
    /**
     * Get columns used by the this service,
     * to get context metrics.
     */
    public function getStatsColumns(): array
    {
        return [
            StatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID,
            StatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID,
            StatisticsHelper::STATISTICS_DIMENSION_COUNTRY,
            StatisticsHelper::STATISTICS_DIMENSION_REGION,
            StatisticsHelper::STATISTICS_DIMENSION_CITY,
            StatisticsHelper::STATISTICS_DIMENSION_MONTH,
        ];
    }

    public function getTotalCount(array $args, string $scale): int
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge($defaultArgs, $args);
        unset($args['count']);
        unset($args['offset']);
        $metricsQB = $this->getQueryBuilder($args);

        HookRegistry::call('StatsGeo::getTotalCountriesCount::queryBuilder', [&$metricsQB, $args]);

        $groupBy = [];
        if ($scale == StatisticsHelper::STATISTICS_DIMENSION_CITY) {
            $groupBy = [StatisticsHelper::STATISTICS_DIMENSION_COUNTRY, StatisticsHelper::STATISTICS_DIMENSION_REGION, StatisticsHelper::STATISTICS_DIMENSION_CITY];
        } elseif ($scale == StatisticsHelper::STATISTICS_DIMENSION_REGION) {
            $groupBy = [StatisticsHelper::STATISTICS_DIMENSION_COUNTRY, StatisticsHelper::STATISTICS_DIMENSION_REGION];
        } elseif ($scale == StatisticsHelper::STATISTICS_DIMENSION_COUNTRY) {
            $groupBy = [StatisticsHelper::STATISTICS_DIMENSION_COUNTRY];
        }
        $metricsQB = $metricsQB->getSum($groupBy);

        return $metricsQB->get()->count();
    }

    public function getTotalMetrics(array $args, string $scale): array
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge($defaultArgs, $args);
        $metricsQB = $this->getQueryBuilder($args);

        HookRegistry::call('StatsGeo::getTotalCountriesMetrics::queryBuilder', [&$metricsQB, $args]);

        $groupBy = [];
        if ($scale == StatisticsHelper::STATISTICS_DIMENSION_CITY) {
            $groupBy = [StatisticsHelper::STATISTICS_DIMENSION_COUNTRY, StatisticsHelper::STATISTICS_DIMENSION_REGION, StatisticsHelper::STATISTICS_DIMENSION_CITY];
        } elseif ($scale == StatisticsHelper::STATISTICS_DIMENSION_REGION) {
            $groupBy = [StatisticsHelper::STATISTICS_DIMENSION_COUNTRY, StatisticsHelper::STATISTICS_DIMENSION_REGION];
        } elseif ($scale == StatisticsHelper::STATISTICS_DIMENSION_COUNTRY) {
            $groupBy = [StatisticsHelper::STATISTICS_DIMENSION_COUNTRY];
        }
        $metricsQB = $metricsQB->getSum($groupBy);

        $args['orderDirection'] === StatisticsHelper::STATISTICS_ORDER_ASC ? 'asc' : 'desc';
        $metricsQB->orderBy(StatisticsHelper::STATISTICS_METRIC_INVESTIGATIONS, $args['orderDirection']);

        if (isset($args['count'])) {
            $metricsQB->limit($args['count']);
            if (isset($args['offset'])) {
                $metricsQB->offset($args['offset']);
            }
        }

        return $metricsQB->get()->toArray();
    }


    /**
     * @copydoc PKPStatsServie::prepareStatsArgs()
     */
    public function prepareStatsArgs(array $filters = []): array
    {
        $args = [];
        $validColumns = $this->getStatsColumns();
        foreach ($filters as $filterColumn => $value) {
            if (!in_array($filterColumn, $validColumns)) {
                continue;
            }
            switch ($filterColumn) {
                case StatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID:
                    $args['contextIds'] = $value;
                    break;
                case StatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID:
                    $args['submissionIds'] = $value;
                    break;
                case StatisticsHelper::STATISTICS_DIMENSION_COUNTRY:
                    $args['countries'] = $value;
                    break;
                case StatisticsHelper::STATISTICS_DIMENSION_REGION:
                    $args['regions'] = $value;
                    break;
                case StatisticsHelper::STATISTICS_DIMENSION_CITY:
                    $args['cities'] = $value;
                    break;
                case StatisticsHelper::STATISTICS_DIMENSION_MONTH:
                case StatisticsHelper::STATISTICS_DIMENSION_DAY:
                    $args['timeInterval'] = $filterColumn;
                    if (isset($value['from'])) {
                        $args['dateStart'] = $value['from'];
                    }
                    if (isset($value['to'])) {
                        $args['dateEnd'] = $value['to'];
                    }
                    break;
            }
        }
        return $args;
    }

    /**
     * @copydoc PKPStatsService::getDefaultArgs()
     */
    public function getDefaultArgs(): array
    {
        return [
            'dateStart' => StatisticsHelper::STATISTICS_EARLIEST_DATE,
            'dateEnd' => date('Y-m-d', strtotime('yesterday')),

            // Require a context to be specified to prevent unwanted data leakage
            // if someone forgets to specify the context. If you really want to
            // get data across all contexts, pass an empty `contextId` arg.
            'contextIds' => [\PKP\core\PKPApplication::CONTEXT_ID_NONE],
        ];
    }

    /**
     * Get a QueryBuilder object with the passed args
     *
     * @param array $args See self::prepareStatsArgs()
     *
     */
    public function getQueryBuilder($args = []): \PKP\services\queryBuilders\PKPStatsGeoQueryBuilder
    {
        $statsQB = new \PKP\services\queryBuilders\PKPStatsGeoQueryBuilder();
        $statsQB
            ->filterByContexts($args['contextIds'])
            ->before($args['dateEnd'])
            ->after($args['dateStart']);

        if (!empty($args['submissionIds'])) {
            $statsQB->filterBySubmissions($args['submissionIds']);
        }
        if (!empty($args['countries'])) {
            $statsQB->filterByCountries($args['countries']);
        }
        if (!empty($args['regions'])) {
            $statsQB->filterByRegions($args['regions']);
        }
        if (!empty($args['cities'])) {
            $statsQB->filterByCities($args['cities']);
        }

        HookRegistry::call('StatsGeo::queryBuilder', [&$statsQB, $args]);

        return $statsQB;
    }
}
