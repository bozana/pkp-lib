<?php

/**
 * @file classes/statistics/UsageStatsUniqueTemporaryRecordDAO.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UsageStatsUniqueTemporaryRecordDAO
 * @ingroup statistics
 *
 * @brief Operations for retrieving and adding unique temporary usage statistics records.
 */

namespace PKP\statistics;

use Illuminate\Support\Facades\DB;

class UsageStatsUniqueTemporaryRecordDAO
{
    /** @var string The name of the table */
    public $table = 'usage_stats_unique_temporary_records';


    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Add the passed usage statistic record.
     *
     * @param array $entryData [
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
    public function insert(array $entryData, int $lineNumber, string $loadId)
    {
        $file = 'debug.txt';
        $current = file_get_contents($file);
        $current .= print_r("++++ insert into usage_stats_unique_temporary_record ++++\n", true);
        file_put_contents($file, $current);

        DB::table($this->table)->insert([
            'date' => $entryData['time'],
            'line_number' => $lineNumber,
            'issue_id' => array_key_exists('issueId', $entryData) ? $entryData['issueId'] : null,
            'context_id' => $entryData['contextId'],
            'submission_id' => $entryData['submissionId'],
            'representation_id' => $entryData['representationId'],
            'assoc_type' => $entryData['assocType'],
            'assoc_id' => $entryData['assocId'],
            'file_type' => $entryData['fileType'],
            'country' => $entryData['country'],
            'region' => $entryData['region'],
            'city' => $entryData['city'],
            'institution_ids' => implode('-', $entryData['institutionIds']),
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
     * Delete the record with the passed assoc id and type with
     * the most recent day value.
     */
    public function deleteRecord(int $contextId, int $submissionId, int $lineNumber, $time, string $loadId)
    {
        DB::table($this->table)
            ->where('context_id', '=', $contextId)
            ->where('submission_id', '=', $submissionId)
            ->where('line_number', '=', $lineNumber)
            ->where('date', '=', $time)
            ->where('load_id', '=', $loadId)
            ->delete();
    }
}
