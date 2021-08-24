<?php

/**
 * @file classes/services/PKPStatsContextService.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsContextService
 * @ingroup services
 *
 * @brief Helper class that encapsulates context statistics business logic
 */

namespace PKP\services;

use APP\statistics\StatisticsHelper;
use PKP\plugins\HookRegistry;

class PKPStatsContextService extends PKPStatsService
{
    /**
     * Get columns used by the this service,
     * to get context metrics.
     */
    public function getStatsColumns(): array
    {
        return [
            StatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID,
            StatisticsHelper::STATISTICS_DIMENSION_ASSOC_TYPE,
            StatisticsHelper::STATISTICS_DIMENSION_MONTH,
            StatisticsHelper::STATISTICS_DIMENSION_DAY,
        ];
    }

    public function getTotalCount(array $args): int
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge($defaultArgs, $args);
        $metricsQB = $this->getQueryBuilder($args);

        HookRegistry::call('StatsContext::getTotalCount::queryBuilder', [&$metricsQB, $args]);

        $groupBy = [StatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID];
        $metricsQB = $metricsQB->getSum($groupBy);

        return $metricsQB->get()->count();
    }

    public function getTotalMetrics(array $args): array
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge($defaultArgs, $args);
        $metricsQB = $this->getQueryBuilder($args);

        HookRegistry::call('StatsContext::getTotalMetrics::queryBuilder', [&$metricsQB, $args]);

        $groupBy = [StatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID];
        $metricsQB = $metricsQB->getSum($groupBy);

        $args['orderDirection'] === StatisticsHelper::STATISTICS_ORDER_ASC ? 'asc' : 'desc';
        $metricsQB->orderBy(StatisticsHelper::STATISTICS_METRIC, $args['orderDirection']);

        if (isset($args['count'])) {
            $metricsQB->limit($args['count']);
            if (isset($args['offset'])) {
                $metricsQB->offset($args['offset']);
            }
        }

        return $metricsQB->get()->toArray();
    }

    public function getMetricsForContext(array $args): array
    {
        $defaultArgs = $this->getDefaultArgs();
        $args = array_merge($defaultArgs, $args);
        $metricsQB = $this->getQueryBuilder($args);
        HookRegistry::call('StatsContext::getMetricsByType::queryBuilder', [&$metricsQB, $args]);
        $metricsQB = $metricsQB->getSum([]);
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
        ];
    }

    /**
     * Get a QueryBuilder object with the passed args
     *
     * @param array $args See self::prepareStatsArgs()
     *
     */
    public function getQueryBuilder(array $args = []): \PKP\services\queryBuilders\PKPStatsContextQueryBuilder
    {
        $statsQB = new \PKP\services\queryBuilders\PKPStatsContextQueryBuilder();
        $statsQB
            ->before($args['dateEnd'])
            ->after($args['dateStart']);
        if (!empty($args['contextIds'])) {
            $statsQB->filterByContexts($args['contextIds']);
        }
        HookRegistry::call('StatsContext::queryBuilder', [&$statsQB, $args]);
        return $statsQB;
    }
}
