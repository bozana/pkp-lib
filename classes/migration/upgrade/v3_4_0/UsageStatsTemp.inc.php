<?php

/**
 * @file classes/migration/upgrade/v3_4_0/UsageStatsTemp.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UsageStatsTemp
 * @brief Describe upgrade/downgrade operations for DB table usage_stats_total_temporary_records and usage_stats_unique_temporary_records.
 */

namespace PKP\migration\upgrade\v3_4_0;

use APP\core\Application;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\config\Config;

class UsageStatsTemp extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        if (!Schema::hasTable('metrics_submission')) {
            Schema::create('metrics_submission', function (Blueprint $table) {
                $table->string('load_id', 255);
                $table->bigInteger('context_id');
                $table->bigInteger('submission_id');
                $table->bigInteger('representation_id')->nullable();
                $table->bigInteger('file_id')->nullable();
                $table->bigInteger('file_type')->nullable();
                $table->smallInteger('primary_file')->default(0);
                $table->date('date');
                $table->integer('metric');
                $table->index(['load_id'], 'metrics_submission_load_id');
            });
        }
        /*
        Schema::create('usage_stats_institution_temporary_records', function (Blueprint $table) {
            $table->string('load_id', 255);
            $table->bigInteger('line_number');
            $table->bigInteger('institution_id');
        });
        if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
            DB::statement('ALTER TABLE usage_stats_institution_temporary_records ADD CONSTRAINT uc_load_id_line_number_institution_id UNIQUE INCLUDE (load_id, line_number, institution_id)');
        } else {
            DB::statement('ALTER TABLE usage_stats_institution_temporary_records ADD CONSTRAINT uc_load_id_line_number_institution_id UNIQUE (load_id, line_number, institution_id)');
        }
        */

/*
        // Usage stats total temporary records
        Schema::create('usage_stats_total_temporary_records', function (Blueprint $table) {
            $table->dateTime('date', $precision = 0);
            $table->string('ip', 255);
            $table->string('user_agent', 255);
            $table->bigInteger('line_number');
            $table->string('canonical_url', 255);
            $table->bigInteger('issue_id')->nullable();
            $table->bigInteger('context_id');
            $table->bigInteger('submission_id')->nullable();
            $table->bigInteger('representation_id')->nullable();
            $table->bigInteger('assoc_type');
            $table->bigInteger('assoc_id');
            $table->smallInteger('file_type')->nullable();
            $table->string('country', 2)->default('');
            $table->string('region', 3)->default('');
            $table->string('city', 255)->default('');
            $table->json('institution_ids');
            $table->string('load_id', 255);
        });

        // Usage stats unique temporary records
        Schema::create('usage_stats_unique_temporary_records', function (Blueprint $table) {
            $table->dateTime('date', $precision = 0);
            $table->string('ip', 255);
            $table->string('user_agent', 255);
            $table->bigInteger('line_number');
            $table->bigInteger('issue_id')->nullable();
            $table->bigInteger('context_id');
            $table->bigInteger('submission_id');
            $table->bigInteger('representation_id')->nullable();
            $table->bigInteger('assoc_type');
            $table->bigInteger('assoc_id');
            $table->smallInteger('file_type')->nullable();
            $table->string('country', 2)->default('');
            $table->string('region', 3)->default('');
            $table->string('city', 255)->default('');
            $table->json('institution_ids');
            $table->string('load_id', 255);
        });
*/

        /*
                // metrics_context:
                // load_id, context_id, date, metric
                DB::statement('
                INSERT INTO metrics_context (load_id, context_id, date, metric)
                    SELECT load_id, context_id, DATE(date) as date, count(*) as metric
                    FROM usage_stats_total_temporary_records
                    WHERE assoc_type = ' . Application::getContextAssocType() . '
                    GROUP BY load_id, context_id, DATE(date)
                ');

                // metrics_issue:
                // load_id, context_id, issue_id, issue_galley_id, date, metric
                DB::statement('
                    INSERT INTO metrics_issue (load_id, context_id, issue_id, date, metric)
                        SELECT load_id, context_id, issue_id, DATE(date) as date, count(*) as metric
                        FROM usage_stats_total_temporary_records
                        WHERE assoc_type = ' . Application::ASSOC_TYPE_ISSUE . '
                        GROUP BY load_id, context_id, issue_id, DATE(date)
                ');
                DB::statement('
                    INSERT INTO metrics_issue (load_id, context_id, issue_id, issue_galley_id, date, metric)
                        SELECT load_id, context_id, issue_id, assoc_id, DATE(date) as date, count(*) as metric
                        FROM usage_stats_total_temporary_records
                        WHERE assoc_type = ' . Application::ASSOC_TYPE_ISSUE_GALLEY . '
                        GROUP BY load_id, context_id, issue_id, assoc_id, DATE(date)
                ');

                // metrics_submission:
                // load_id, context_id, submission_id, representation_id, file_id, file_type, date, metric
                DB::statement('
                    INSERT INTO metrics_submission (load_id, context_id, submission_id, date, metric)
                    SELECT load_id, context_id, submission_id, DATE(date) as date, count(*) as metric
                    FROM usage_stats_total_temporary_records
                    WHERE assoc_type = ' . Application::ASSOC_TYPE_SUBMISSION . '
                    GROUP BY load_id, context_id, submission_id, DATE(date)
                ');
                DB::statement('
                    INSERT INTO metrics_submission (load_id, context_id, submission_id, representation_id, file_id, file_type, date, metric)
                    SELECT load_id, context_id, submission_id, representation_id, assoc_id, file_type, DATE(date) as date, count(*) as metric
                    FROM usage_stats_total_temporary_records
                    WHERE assoc_type = ' . Application::ASSOC_TYPE_SUBMISSION_FILE . '
                    GROUP BY load_id, context_id, submission_id, representation_id, assoc_id, file_type, DATE(date)
                ');

                // metrics_counter_submission_daily:
                // load_id, context_id, submission_id, date, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique
                DB::statement('
                    INSERT INTO metrics_counter_submission_daily (load_id, context_id, submission_id, date, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                        SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, count(*) as metric, 0 as metric_investigations_unique, 0 as metric_requests, 0 as metric_requests_unique
                            FROM usage_stats_total_temporary_records
                            WHERE assoc_type = ' . Application::ASSOC_TYPE_SUBMISSION . '
                            GROUP BY load_id, context_id, submission_id, DATE(date)) AS t
                    ON DUPLICATE KEY UPDATE metric_investigations = metric;
                ');
                DB::statement('
                    INSERT INTO metrics_counter_submission_daily (load_id, context_id, submission_id, date, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                        SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, 0 as metric_investigations, count(*) as metric, 0 as metric_requests, 0 as metric_requests_unique
                            FROM usage_stats_unique_temporary_records
                            WHERE assoc_type = ' . Application::ASSOC_TYPE_SUBMISSION . '
                            GROUP BY load_id, context_id, submission_id, DATE(date)) AS t
                    ON DUPLICATE KEY UPDATE metric_investigations_unique = metric;
                ');
                DB::statement('
                    INSERT INTO metrics_counter_submission_daily (load_id, context_id, submission_id, date, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                        SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, 0 as metric_investigations, 0 as metric_investigations_unique, count(*) as metric, 0 as metric_requests_unique
                            FROM usage_stats_total_temporary_records
                            WHERE assoc_type = ' . Application::ASSOC_TYPE_SUBMISSION_FILE . '
                            GROUP BY load_id, context_id, submission_id, DATE(date)) AS t
                    ON DUPLICATE KEY UPDATE metric_requests = metric;
                ');
                DB::statement('
                    INSERT INTO metrics_counter_submission_daily (load_id, context_id, submission_id, date, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                        SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, 0 as metric_investigations, 0 as metric_investigations_unique, 0 as metric_requests, count(*) as metric
                            FROM usage_stats_unique_temporary_records
                            WHERE assoc_type = ' . Application::ASSOC_TYPE_SUBMISSION_FILE . '
                            GROUP BY load_id, context_id, submission_id, DATE(date)) AS t
                    ON DUPLICATE KEY UPDATE metric_requests_unique = metric;
                ');

                // metrics_counter_submission_geo_daily:
                // load_id, context_id, submission_id, date, country, region, city, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique
                DB::statement('
                    INSERT INTO metrics_counter_submission_geo_daily (load_id, context_id, submission_id, date, country, region, city, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                        SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, country, region, city, count(*) as metric, 0 as metric_investigations_unique, 0 as metric_requests, 0 as metric_requests_unique
                            FROM usage_stats_total_temporary_records
                            WHERE assoc_type = ' . Application::ASSOC_TYPE_SUBMISSION . '
                            GROUP BY load_id, context_id, submission_id, DATE(date), country, region, city) AS t
                    ON DUPLICATE KEY UPDATE metric_investigations = metric;
                ');
                DB::statement('
                    INSERT INTO metrics_counter_submission_geo_daily (load_id, context_id, submission_id, date, country, region, city, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                        SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, country, region, city, 0 as metric_investigations, count(*) as metric, 0 as metric_requests, 0 as metric_requests_unique
                            FROM usage_stats_unique_temporary_records
                            WHERE assoc_type = ' . Application::ASSOC_TYPE_SUBMISSION . '
                            GROUP BY load_id, context_id, submission_id, DATE(date), country, region, city) AS t
                    ON DUPLICATE KEY UPDATE metric_investigations_unique = metric;
                ');
                DB::statement('
                    INSERT INTO metrics_counter_submission_geo_daily (load_id, context_id, submission_id, date, country, region, city, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                        SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, country, region, city, 0 as metric_investigations, 0 as metric_investigations_unique, count(*) as metric, 0 as metric_requests_unique
                            FROM usage_stats_total_temporary_records
                            WHERE assoc_type = ' . Application::ASSOC_TYPE_SUBMISSION_FILE . '
                            GROUP BY load_id, context_id, submission_id, DATE(date), country, region, city) AS t
                    ON DUPLICATE KEY UPDATE metric_requests = metric;
                ');
                DB::statement('
                    INSERT INTO metrics_counter_submission_geo_daily (load_id, context_id, submission_id, date, country, region, city, metric_investigations, metric_investigations_unique, metric_requests, metric_requests_unique)
                        SELECT * FROM (SELECT load_id, context_id, submission_id, DATE(date) as date, country, region, city, 0 as metric_investigations, 0 as metric_investigations_unique, 0 as metric_requests, count(*) as metric
                            FROM usage_stats_unique_temporary_records
                            WHERE assoc_type = ' . Application::ASSOC_TYPE_SUBMISSION_FILE . '
                            GROUP BY load_id, context_id, submission_id, DATE(date), country, region, city) AS t
                    ON DUPLICATE KEY UPDATE metric_requests_unique = metric;
                ');
        */
        /*
                $file = 'debug.txt';
                $current = file_get_contents($file);
                $current .= print_r("++++ result ++++\n", true);
                $current .= print_r($rows4, true);
                file_put_contents($file, $current);
        */
    }

    /**
     * Reverse the downgrades
     */
    public function down()
    {
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\migration\upgrade\v3_4_0\UsageStatsTemp', '\UsageStatsTemp');
}
