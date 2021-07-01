<?php

/**
 * @file classes/statistics/UsageStatsUniqueRequestsTemporaryRecordDAO.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UsageStatsUniqueRequestsTemporaryRecordDAO
 * @ingroup statistics
 *
 * @brief Operations for retrieving and adding unique item requests.
 */

namespace PKP\statistics;

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\db\DAORegistry;

class UsageStatsUniqueRequestsTemporaryRecordDAO
{
    /** @var string The name of the table */
    public $table = 'usage_stats_unique_requests_temporary_records';


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
            DB::statement("DELETE FROM {$this->table} usur WHERE EXISTS (SELECT * FROM (SELECT 1 FROM {$this->table} usurt WHERE usurt.load_id = usur.load_id AND usurt.ip = usur.ip AND usurt.user_agent = usur.user_agent AND usurt.context_id = usur.context_id AND usurt.submission_id = usur.submission_id AND TIMESTAMPDIFF(HOUR, usur.date, usurt.date) = 0 AND usur.line_number < usurt.line_number) AS tmp)");
        }
    }

    public function loadMetricsCounterSubmissionDaily(string $loadId): bool
    {
        // s. https://dev.mysql.com/doc/refman/8.0/en/insert-on-duplicate.html
        return DB::statement(
            "
            INSERT INTO metrics_counter_submission_daily (load_id, context_id, submission_id, date, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, 0 as metric_investigations, 0 as metric_investigations_unique, 0 as metric_requests, count(*) as metric
                    FROM {$this->table}
                    WHERE load_id = ? AND assoc_type = ?
                    GROUP BY load_id, context_id, submission_id, DATE(date)) AS t
            ON DUPLICATE KEY UPDATE metric_requests_unique = metric;
            ",
            [$loadId, Application::ASSOC_TYPE_SUBMISSION_FILE]
        );
    }

    public function loadMetricsCounterSubmissionGeoDaily(string $loadId): bool
    {
        return DB::statement(
            "
            INSERT INTO metrics_counter_submission_geo_daily (load_id, context_id, submission_id, date, country, region, city, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, country, region, city, 0 as metric_investigations, 0 as metric_investigations_unique, 0 as metric_requests, count(*) as metric
                    FROM {$this->table}
                    WHERE load_id = ? AND assoc_type = ?
                    GROUP BY load_id, context_id, submission_id, DATE(date), country, region, city) AS t
            ON DUPLICATE KEY UPDATE metric_requests_unique = metric;
            ",
            [$loadId, Application::ASSOC_TYPE_SUBMISSION_FILE]
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
                        SELECT usur.load_id, usur.context_id, usur.submission_id, DATE(usur.date) as date, usi.institution_id, 0 as metric_investigations, 0 as metric_investigations_unique, 0 as metric_requests, count(*) as metric
                        FROM {$this->table} usur
                        JOIN usage_stats_institution_temporary_records usi on (usi.load_id = usur.load_id AND usi.line_number = usur.line_number)
                        WHERE usur.load_id = ? AND usur.assoc_type = ? AND usi.institution_id = ?
                        GROUP BY load_id, context_id, submission_id, DATE(date), institution_id) AS t
                ON DUPLICATE KEY UPDATE metric_requests_unique = metric;
                ",
                [$loadId, Application::ASSOC_TYPE_SUBMISSION_FILE, (int) $institutionId]
            );
            if (!$result) {
                return false;
            }
        }
        return true;
    }
}
