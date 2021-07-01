<?php

/**
 * @file classes/statistics/UsageStatsUniqueInvestigationsTemporaryRecordDAO.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UsageStatsUniqueInvestigationsTemporaryRecordDAO
 * @ingroup statistics
 *
 * @brief Operations for retrieving and adding unique item investigations.
 */

namespace PKP\statistics;

use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\db\DAORegistry;

class UsageStatsUniqueInvestigationsTemporaryRecordDAO
{
    /** @var string The name of the table */
    public $table = 'usage_stats_unique_investigations_temporary_records';


    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Add the passed usage statistic record.
     *
     * @param \stdClass $entryData [
     * 	issue_id
     *  time
     *  ip
     *  canonicalUrl
     *  contextId
     *  submissionId
     *  representationId
     *  assocType
     *  assocId
     *  fileType
     *  userAgent
     *  country
     *  region
     *  city
     *  instituionIds
     * ]
     */
    public function insert(\stdClass $entryData, int $lineNumber, string $loadId)
    {
        DB::table($this->table)->insert([
            'date' => $entryData->time,
            'ip' => $entryData->ip,
            'user_agent' => substr($entryData->userAgent, 0, 255),
            'line_number' => $lineNumber,
            'issue_id' => !empty($entryData->ssueId) ? $entryData->issueId : null,
            'context_id' => $entryData->contextId,
            'submission_id' => $entryData->submissionId,
            'representation_id' => $entryData->representationId,
            'assoc_type' => $entryData->assocType,
            'assoc_id' => $entryData->assocId,
            'file_type' => $entryData->fileType,
            'country' => !empty($entryData->country) ? $entryData->country : '',
            'region' => !empty($entryData->region) ? $entryData->region : '',
            'city' => !empty($entryData->city) ? $entryData->city : '',
            'institution_ids' => json_encode($entryData->institutionIds),
            'load_id' => $loadId,
        ]);
    }

    /**
     * Delete all temporary records associated
     * with the passed load id.
     */
    public function deleteByLoadId(string $loadId)
    {
        DB::table($this->table)->where('load_id', '=', $loadId)->delete();
    }

    /**
     * Remove Unique Clicks
     * See https://www.projectcounter.org/code-of-practice-five-sections/7-processing-rules-underlying-counter-reporting-data/#counting
     */
    public function removeUniqueClicks()
    {
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            // TO-DO
        } else {
            DB::statement("DELETE FROM {$this->table} usui WHERE EXISTS (SELECT * FROM (SELECT 1 FROM {$this->table} usuit WHERE usuit.load_id = usui.load_id AND usuit.ip = usui.ip AND usuit.user_agent = usui.user_agent AND usuit.context_id = usui.context_id AND usuit.submission_id = usui.submission_id AND TIMESTAMPDIFF(HOUR, usui.date, usuit.date) = 0 AND usui.line_number < usuit.line_number) AS tmp)");
        }
    }

    public function loadMetricsCounterSubmissionDaily(string $loadId): bool
    {
        // s. https://dev.mysql.com/doc/refman/8.0/en/insert-on-duplicate.html
        return DB::statement(
            "
            INSERT INTO metrics_counter_submission_daily (load_id, context_id, submission_id, date, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, 0 as metric_investigations, count(*) as metric, 0 as metric_requests, 0 as metric_requests_unique
                    FROM {$this->table}
                    WHERE load_id = ? AND submission_id IS NOT NULL
                    GROUP BY load_id, context_id, submission_id, DATE(date)) AS t
            ON DUPLICATE KEY UPDATE metric_investigations_unique = metric;
            ",
            [$loadId]
        );
    }

    public function loadMetricsCounterSubmissionGeoDaily(string $loadId): bool
    {
        return DB::statement(
            "
            INSERT INTO metrics_counter_submission_geo_daily (load_id, context_id, submission_id, date, country, region, city, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, country, region, city, 0 as metric_investigations, count(*) as metric, 0 as metric_requests, 0 as metric_requests_unique
                    FROM {$this->table}
                    WHERE load_id = ? AND submission_id IS NOT NULL
                    GROUP BY load_id, context_id, submission_id, DATE(date), country, region, city) AS t
            ON DUPLICATE KEY UPDATE metric_investigations_unique = metric;
            ",
            [$loadId]
        );
    }

    public function loadMetricsCounterSubmissionInstitutionDaily(string $loadId): bool
    {
        $statsInstitutionDao = DAORegistry::getDAO('UsageStatsInstitutionTemporaryRecordDAO'); /* @var $statsInstitutionDao UsageStatsInstitutionTemporaryRecordDAO */
        $institutionIds = $statsInstitutionDao->getInstitutionIdsByLoadId($loadId);
        foreach ($institutionIds as $institutionId) {
            $result = DB::statement(
                "
                INSERT INTO metrics_counter_submission_institution_daily (load_id, context_id, submission_id, date, institution_id, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                    SELECT * FROM (
                        SELECT usui.load_id, usui.context_id, usui.submission_id, DATE(usui.date) as date, usi.institution_id, 0 as metric_investigations, count(*) as metric, 0 as metric_requests, 0 as metric_requests_unique
                        FROM {$this->table} usui
                        JOIN usage_stats_institution_temporary_records usi on (usi.load_id = usui.load_id AND usi.line_number = usui.line_number)
                        WHERE usui.load_id = ? AND submission_id IS NOT NULL AND usi.institution_id = ?
                        GROUP BY load_id, context_id, submission_id, DATE(date), institution_id) AS t
                ON DUPLICATE KEY UPDATE metric_investigations_unique = metric;
                ",
                [$loadId, (int) $institutionId]
            );
            if (!$result) {
                return false;
            }
        }
        return true;
    }
}
