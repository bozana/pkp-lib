<?php

/**
 * @file classes/statistics/UsageStatsTotalTemporaryRecordDAO.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UsageStatsTotalTemporaryRecordDAO
 * @ingroup statistics
 *
 * @brief Operations for retrieving and adding total temporary usage statistics records.
 */

namespace PKP\statistics;

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\db\DAORegistry;

class UsageStatsTotalTemporaryRecordDAO
{
    /** @var string The name of the table */
    public $table = 'usage_stats_total_temporary_records';


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
     *  canonicalURL
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
            'canonical_url' => $entryData->canonicalUrl,
            'issue_id' => property_exists($entryData, 'issueId') ? $entryData->issueId : null,
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

    public function checkForeignKeys(\stdClass $entryData): array
    {
        $errorMsg = [];
        $contextDao = Application::getContextDAO();
        $representationDao = \APP\core\Application::getRepresentationDAO();
        if (DB::table($contextDao->tableName)->where($contextDao->primaryKeyColumn, '=', $entryData->contextId)->doesntExist()) {
            $errorMsg[] = "{$contextDao->primaryKeyColumn}: {$entryData->contextId}";
        }
        if (!empty($entryData->issueId) && DB::table('issues')->where('issue_id', '=', $entryData->issueId)->doesntExist()) {
            $errorMsg[] = "issue_id: {$entryData->issueId}";
        }
        if (!empty($entryData->submissionId) && DB::table('submissions')->where('submission_id', '=', $entryData->submissionId)->doesntExist()) {
            $errorMsg[] = "submission_id: {$entryData->submissionId}";
        }
        if (!empty($entryData->representationId) && DB::table($representationDao->tableName)->where($representationDao->primaryKeyColumn, '=', $entryData->representationId)->doesntExist()) {
            $errorMsg[] = "{$representationDao->primaryKeyColumn}: {$entryData->representationId}";
        }

        if (in_array($entryData->assoc_type, [Application::ASSOC_TYPE_SUBMISSION_FILE, Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER]) &&
            DB::table('submission_files')->where('submission_file_id', '=', $entryData->assocId)->doesntExist()) {
            $errorMsg[] = "submission_file_id: {$entryData->assocId}";
        }
        if (($entryData->assoc_type == Application::ASSOC_TYPE_ISSUE_GALLEY) &&
            DB::table('issue_galleys')->where('galley_id', '=', $entryData->assocId)->doesntExist()) {
            $errorMsg[] = "issue_galley_id: {$entryData->assocId}";
        }
        foreach ($entryData->institutionIds as $institutionId) {
            if (DB::table('institutions')->where('institution_id', '=', $institutionId)->doesntExist()) {
                $errorMsg[] = "institution_id: {$institutionId}";
            }
        }
        return $errorMsg;
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
     * Remove Double Clicks
     * See https://www.projectcounter.org/code-of-practice-five-sections/7-processing-rules-underlying-counter-reporting-data/#doubleclick
     */
    public function removeDoubleClicks(int $counterDoubleClickTimeFilter)
    {
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            // TO-DO
        } else {
            DB::statement("DELETE FROM {$this->table} ust WHERE EXISTS (SELECT * FROM (SELECT 1 FROM {$this->table} ustt WHERE ustt.load_id = ust.load_id AND ustt.ip = ust.ip AND ustt.user_agent = ust.user_agent AND ustt.canonical_url = ust.canonical_url AND TIMESTAMPDIFF(SECOND, ust.date, ustt.date) < ? AND TIMESTAMPDIFF(SECOND, ust.date, ustt.date) > 0 AND ust.line_number < ustt.line_number) AS tmp)", [$counterDoubleClickTimeFilter]);
        }
    }

    public function loadMetricsContext(string $loadId): bool
    {
        DB::table('metrics_context')->where('load_id', '=', $loadId)->delete();
        return DB::statement(
            "
            INSERT INTO metrics_context (load_id, context_id, date, metric)
                SELECT load_id, context_id, DATE(date) as date, count(*) as metric
                FROM {$this->table}
                WHERE load_id = ? AND assoc_type = ?
                GROUP BY load_id, context_id, DATE(date)
            ",
            [$loadId, Application::getContextAssocType()]
        );
    }

    public function loadMetricsIssue(string $loadId): bool
    {
        DB::table('metrics_issue')->where('load_id', '=', $loadId)->delete();
        $result = DB::statement(
            "
            INSERT INTO metrics_issue (load_id, context_id, issue_id, date, metric)
                SELECT load_id, context_id, issue_id, DATE(date) as date, count(*) as metric
                FROM {$this->table}
                WHERE load_id = ? AND assoc_type = ?
                GROUP BY load_id, context_id, issue_id, DATE(date)
            ",
            [$loadId, Application::ASSOC_TYPE_ISSUE]
        );
        if ($result) {
            return DB::statement(
                "
                INSERT INTO metrics_issue (load_id, context_id, issue_id, issue_galley_id, date, metric)
                    SELECT load_id, context_id, issue_id, assoc_id, DATE(date) as date, count(*) as metric
                    FROM {$this->table}
                    WHERE load_id = ? AND assoc_type = ?
                    GROUP BY load_id, context_id, issue_id, assoc_id, DATE(date)
                ",
                [$loadId, Application::ASSOC_TYPE_ISSUE_GALLEY]
            );
        }
        return $result;
    }

    public function loadMetricsSubmission(string $loadId): bool
    {
        DB::table('metrics_submission')->where('load_id', '=', $loadId)->delete();
        $result = DB::statement(
            '
            INSERT INTO metrics_submission (load_id, context_id, submission_id, assoc_type, date, metric)
                SELECT load_id, context_id, submission_id, ' . Application::ASSOC_TYPE_SUBMISSION . ", DATE(date) as date, count(*) as metric
                FROM {$this->table}
                WHERE load_id = ? AND assoc_type = ?
                GROUP BY load_id, context_id, submission_id, DATE(date)
            ",
            [$loadId, Application::ASSOC_TYPE_SUBMISSION]
        );
        if ($result) {
            $result = DB::statement(
                '
                INSERT INTO metrics_submission (load_id, context_id, submission_id, representation_id, file_id, file_type, assoc_type, date, metric)
                    SELECT load_id, context_id, submission_id, representation_id, assoc_id, file_type, ' . Application::ASSOC_TYPE_SUBMISSION_FILE . ", DATE(date) as date, count(*) as metric
                    FROM {$this->table}
                    WHERE load_id = ? AND assoc_type = ?
                    GROUP BY load_id, context_id, submission_id, representation_id, assoc_id, file_type, DATE(date)
                ",
                [$loadId, Application::ASSOC_TYPE_SUBMISSION_FILE]
            );
        }
        if ($result) {
            return DB::statement(
                '
                INSERT INTO metrics_submission (load_id, context_id, submission_id, representation_id, file_id, file_type, assoc_type, date, metric)
                    SELECT load_id, context_id, submission_id, representation_id, assoc_id, file_type, ' . Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER . ", DATE(date) as date, count(*) as metric
                    FROM {$this->table}
                    WHERE load_id = ? AND assoc_type = ?
                    GROUP BY load_id, context_id, submission_id, representation_id, assoc_id, file_type, DATE(date)
                ",
                [$loadId, Application::ASSOC_TYPE_SUBMISSION_FILE_COUNTER_OTHER]
            );
        }
        return $result;
    }

    public function deleteCounterSubmissionDailyByLoadId(string $loadId)
    {
        DB::table('metrics_counter_submission_daily')->where('load_id', '=', $loadId)->delete();
    }

    public function loadMetricsCounterSubmissionDaily(string $loadId): bool
    {
        // s. https://dev.mysql.com/doc/refman/8.0/en/insert-on-duplicate.html
        $result = DB::statement(
            "
            INSERT INTO metrics_counter_submission_daily (load_id, context_id, submission_id, date, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, count(*) as metric, 0 as metric_investigations_unique, 0 as metric_requests, 0 as metric_requests_unique
                    FROM {$this->table}
                    WHERE load_id = ? AND submission_id IS NOT NULL
                    GROUP BY load_id, context_id, submission_id, DATE(date)) AS t
            ON DUPLICATE KEY UPDATE metric_investigations = metric;
            ",
            [$loadId]
        );
        if ($result) {
            return DB::statement(
                "
                INSERT INTO metrics_counter_submission_daily (load_id, context_id, submission_id, date, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                    SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, 0 as metric_investigations, 0 as metric_investigations_unique, count(*) as metric, 0 as metric_requests_unique
                        FROM {$this->table}
                        WHERE load_id = ? AND assoc_type = ?
                        GROUP BY load_id, context_id, submission_id, DATE(date)) AS t
                ON DUPLICATE KEY UPDATE metric_requests = metric;
                ",
                [$loadId, Application::ASSOC_TYPE_SUBMISSION_FILE]
            );
        }
        return $result;
    }

    public function deleteCounterSubmissionGeoDailyByLoadId(string $loadId)
    {
        DB::table('metrics_counter_submission_geo_daily')->where('load_id', '=', $loadId)->delete();
    }

    public function loadMetricsCounterSubmissionGeoDaily(string $loadId): bool
    {
        $result = DB::statement(
            "
            INSERT INTO metrics_counter_submission_geo_daily (load_id, context_id, submission_id, date, country, region, city, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, country, region, city, count(*) as metric, 0 as metric_investigations_unique, 0 as metric_requests, 0 as metric_requests_unique
                    FROM {$this->table}
                    WHERE load_id = ? AND submission_id IS NOT NULL
                    GROUP BY load_id, context_id, submission_id, DATE(date), country, region, city) AS t
            ON DUPLICATE KEY UPDATE metric_investigations = metric;
            ",
            [$loadId]
        );
        if ($result) {
            return DB::statement(
                "
                INSERT INTO metrics_counter_submission_geo_daily (load_id, context_id, submission_id, date, country, region, city, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                    SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, country, region, city, 0 as metric_investigations, 0 as metric_investigations_unique, count(*) as metric, 0 as metric_requests_unique
                        FROM {$this->table}
                        WHERE load_id = ? AND assoc_type = ?
                        GROUP BY load_id, context_id, submission_id, DATE(date), country, region, city) AS t
                ON DUPLICATE KEY UPDATE metric_requests = metric;
                ",
                [$loadId, Application::ASSOC_TYPE_SUBMISSION_FILE]
            );
        }
        return $result;
    }

    public function deleteCounterSubmissionInstitutionDailyByLoadId(string $loadId)
    {
        DB::table('metrics_counter_submission_institution_daily')->where('load_id', '=', $loadId)->delete();
    }

    public function loadMetricsCounterSubmissionInstitutionDaily(string $loadId): bool
    {
        /*
        $institutionIds1 = DB::table($this->table)
            ->select('institution_ids')
            ->distinct()
            ->get();
        $institutionIds2 = DB::table($this->table)
            ->select('institution_ids')
            ->distinct()
            ->get()->toArray();
        $institutionIds3 = DB::table($this->table)
            ->select('institution_ids')
            ->distinct()
            ->pluck('institution_ids');
            */
        /* if JSON should be used;
        $institutionIds = DB::table($this->table)
            ->select('institution_ids')
            ->distinct()
            ->pluck('institution_ids')
            ->toArray();

        $institutionIdsResult = array_map(function ($json) {
            return json_decode($json, true);
        }, $institutionIds);
        $institutionIdsUnique = array_unique(call_user_func_array('array_merge', $institutionIdsResult));
        foreach ($institutionIdsUnique as $institutionId) {
            $result = DB::statement(
                '
                INSERT INTO metrics_counter_submission_institution_daily (load_id, context_id, submission_id, date, institution_id, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                    SELECT t.load_id, t.context_id, t.submission_id, t.date, ' . $institutionId . ' as institution_id, t.metric, 0 as metric_investigations_unique, 0 as metric_requests, 0 as metric_requests_unique
                    FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, JSON_CONTAINS(institution_ids, \'' . $institutionId . '\') as institution_tmp, count(*) as metric
                        FROM usage_stats_total_temporary_records
                        WHERE load_id = ? AND submission_id IS NOT NULL AND JSON_CONTAINS(institution_ids, \'' . $institutionId . '\')
                        GROUP BY load_id, context_id, submission_id, DATE(date), institution_tmp) AS t
                ON DUPLICATE KEY UPDATE metric_investigations = metric;
                ',
                [$loadId]
            );
            if (!$result) {
                return false;
            }
            $result = DB::statement(
                '
                    INSERT INTO metrics_counter_submission_institution_daily (load_id, context_id, submission_id, date, institution_id, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                        SELECT t.load_id, t.context_id, t.submission_id, t.date, ' . $institutionId . ' as institution_id, 0 as metric_investigations, 0 as metric_investigations_unique, t.metric, 0 as metric_requests_unique
                        FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, JSON_CONTAINS(institution_ids, \'' . $institutionId . '\') as institution_tmp, count(*) as metric
                            FROM usage_stats_total_temporary_records
                            WHERE load_id = ? AND assoc_type = ?  AND JSON_CONTAINS(institution_ids, \'' . $institutionId . '\')
                            GROUP BY load_id, context_id, submission_id, DATE(date), institution_tmp) AS t
                    ON DUPLICATE KEY UPDATE metric_requests = metric;
                    ',
                [$loadId, Application::ASSOC_TYPE_SUBMISSION_FILE]
            );
            if (!$result) {
                return false;
            }
        }
        return true;
        */

        $statsInstitutionDao = DAORegistry::getDAO('UsageStatsInstitutionTemporaryRecordDAO'); /* @var $statsInstitutionDao UsageStatsInstitutionTemporaryRecordDAO */
        $institutionIds = $statsInstitutionDao->getInstitutionIdsByLoadId($loadId);
        foreach ($institutionIds as $institutionId) {
            $result = DB::statement(
                "
                INSERT INTO metrics_counter_submission_institution_daily (load_id, context_id, submission_id, date, institution_id, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                    SELECT * FROM (
                        SELECT ustt.load_id, ustt.context_id, ustt.submission_id, DATE(ustt.date) as date, usit.institution_id, count(*) as metric, 0 as metric_investigations_unique, 0 as metric_requests, 0 as metric_requests_unique
                        FROM {$this->table} ustt
                        JOIN usage_stats_institution_temporary_records usit on (usit.load_id = ustt.load_id AND usit.line_number = ustt.line_number)
                        WHERE ustt.load_id = ? AND submission_id IS NOT NULL AND usit.institution_id = ?
                        GROUP BY load_id, context_id, submission_id, DATE(date), institution_id) AS t
                ON DUPLICATE KEY UPDATE metric_investigations = metric;
                ",
                [$loadId, (int) $institutionId]
            );
            if (!$result) {
                return false;
            }
            $result = DB::statement(
                "
                INSERT INTO metrics_counter_submission_institution_daily (load_id, context_id, submission_id, date, institution_id, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                    SELECT * FROM (
                        SELECT ustt.load_id, ustt.context_id, ustt.submission_id, DATE(ustt.date) as date, usit.institution_id, 0 as metric_investigations, 0 as metric_investigations_unique, count(*) as metric, 0 as metric_requests_unique
                        FROM {$this->table} ustt
                        JOIN usage_stats_institution_temporary_records usit on (usit.load_id = ustt.load_id AND usit.line_number = ustt.line_number)
                        WHERE ustt.load_id = ? AND ustt.assoc_type = ? AND usit.institution_id = ?
                        GROUP BY load_id, context_id, submission_id, DATE(date), institution_id) AS t
                ON DUPLICATE KEY UPDATE metric_requests = metric;
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
