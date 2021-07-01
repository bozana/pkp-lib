<?php

/**
 * @file classes/migration/install/MetricsMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MetricsMigration
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\config\Config;

class MetricsMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // OLAP statistics data table.

        Schema::create('metrics', function (Blueprint $table) {
            $table->string('load_id', 255);
            $table->bigInteger('context_id');
            $table->bigInteger('pkp_section_id')->nullable();
            $table->bigInteger('assoc_object_type')->nullable();
            $table->bigInteger('assoc_object_id')->nullable();
            $table->bigInteger('submission_id')->nullable();
            $table->bigInteger('representation_id')->nullable();
            $table->bigInteger('assoc_type');
            $table->bigInteger('assoc_id');
            $table->string('day', 8)->nullable();
            $table->string('month', 6)->nullable();
            $table->smallInteger('file_type')->nullable();
            $table->string('country_id', 2)->nullable();
            $table->string('region', 2)->nullable();
            $table->string('city', 255)->nullable();
            $table->string('metric_type', 255);
            $table->integer('metric');
            $table->index(['load_id'], 'metrics_load_id');
            $table->index(['metric_type', 'context_id'], 'metrics_metric_type_context_id');
            $table->index(['metric_type', 'submission_id', 'assoc_type'], 'metrics_metric_type_submission_id_assoc_type');
            $table->index(['metric_type', 'context_id', 'assoc_type', 'assoc_id'], 'metrics_metric_type_submission_id_assoc');
        });

        /*
                $contextDao = \APP\core\Application::getContextDAO();
                $contextTable = $contextDao->tableName;
                $contextIdColumn = $contextDao->primaryKeyColumn;
                $representationDao = \APP\core\Application::getRepresentationDAO();
                $representationTable = $representationDao->tableName;
                $representationIdColumn = $representationDao->primaryKeyColumn;

                if (!Schema::hasTable('metrics_context')) {
                    Schema::create('metrics_context', function (Blueprint $table) use ($contextTable, $contextIdColumn) {
                        $table->string('load_id', 255);
                        $table->bigInteger('context_id');
                        $table->date('date');
                        $table->integer('metric');
                        $table->foreign('context_id')->references($contextIdColumn)->on($contextTable);
                        $table->index(['load_id'], 'metrics_context_load_id');
                        $table->index(['context_id'], 'metrics_context_context_id');
                    });
                }
                if (!Schema::hasTable('metrics_issue')) {
                    Schema::create('metrics_issue', function (Blueprint $table) {
                        $table->string('load_id', 255);
                        $table->bigInteger('context_id');
                        $table->bigInteger('issue_id');
                        $table->bigInteger('issue_galley_id')->nullable();
                        $table->date('date');
                        $table->integer('metric');
                        $table->foreign('context_id')->references('journal_id')->on('journals');
                        $table->foreign('issue_id')->references('issue_id')->on('issues');
                        $table->foreign('issue_galley_id')->references('galley_id')->on('issue_galleys');
                        $table->index(['load_id'], 'metrics_issue_load_id');
                        $table->index(['context_id', 'issue_id'], 'metrics_issue_context_id_issue_id');
                    });
                }
                if (!Schema::hasTable('metrics_submission')) {
                    Schema::create('metrics_submission', function (Blueprint $table) use ($contextTable, $contextIdColumn, $representationTable, $representationIdColumn) {
                        $table->string('load_id', 255);
                        $table->bigInteger('context_id');
                        $table->bigInteger('submission_id');
                        $table->bigInteger('representation_id')->nullable();
                        $table->bigInteger('submission_file_id')->nullable();
                        $table->bigInteger('file_type')->nullable();
                        $table->bigInteger('assoc_type');
                        $table->date('date');
                        $table->integer('metric');
                        $table->foreign('context_id')->references($contextIdColumn)->on($contextTable);
                        $table->foreign('submission_id')->references('submission_id')->on('submissions');
                        $table->foreign('representation_id')->references($representationIdColumn)->on($representationTable);
                        $table->foreign('submission_file_id')->references('submission_file_id')->on('submission_files');
                        $table->index(['load_id'], 'metrics_submission_load_id');
                        $table->index(['context_id', 'submission_id', 'assoc_type', 'file_type'], 'metrics_submission_context_id_submission_id_assoc_type_file_type');
                    });
                }
                if (!Schema::hasTable('metrics_counter_submission_daily')) {
                    Schema::create('metrics_counter_submission_daily', function (Blueprint $table) use ($contextTable, $contextIdColumn) {
                        $table->string('load_id', 255);
                        $table->bigInteger('context_id');
                        $table->bigInteger('submission_id');
                        $table->date('date');
                        $table->integer('metric_investigations');
                        $table->integer('metric_investigations_unique');
                        $table->integer('metric_requests');
                        $table->integer('metric_requests_unique');
                        $table->foreign('context_id')->references($contextIdColumn)->on($contextTable);
                        $table->foreign('submission_id')->references('submission_id')->on('submissions');
                        $table->index(['load_id'], 'metrics_counter_submission_daily_load_id');
                        $table->index(['context_id', 'submission_id'], 'metrics_counter_submission_daily_context_id_submission_id');
                    });
                    if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
                        DB::statement('ALTER TABLE metrics_counter_submission_daily ADD CONSTRAINT uc_load_id_context_id_submission_id_date UNIQUE INCLUDE (load_id, context_id, submission_id, date)');
                    } else {
                        DB::statement('ALTER TABLE metrics_counter_submission_daily ADD CONSTRAINT uc_load_id_context_id_submission_id_date UNIQUE (load_id, context_id, submission_id, date)');
                    }
                }
                if (!Schema::hasTable('metrics_counter_submission_monthly')) {
                    Schema::create('metrics_counter_submission_monthly', function (Blueprint $table) use ($contextTable, $contextIdColumn) {
                        $table->bigInteger('context_id');
                        $table->bigInteger('submission_id');
                        $table->string('month', 6);
                        $table->integer('metric_investigations');
                        $table->integer('metric_investigations_unique');
                        $table->integer('metric_requests');
                        $table->integer('metric_requests_unique');
                        $table->foreign('context_id')->references($contextIdColumn)->on($contextTable);
                        $table->foreign('submission_id')->references('submission_id')->on('submissions');
                        $table->index(['context_id', 'submission_id'], 'metrics_counter_submission_monthly_context_id_submission_id');
                    });
                    if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
                        DB::statement('ALTER TABLE metrics_counter_submission_monthly ADD CONSTRAINT uc_context_id_submission_id_month UNIQUE INCLUDE (context_id, submission_id, month)');
                    } else {
                        DB::statement('ALTER TABLE metrics_counter_submission_monthly ADD CONSTRAINT uc_context_id_submission_id_month UNIQUE (context_id, submission_id, month)');
                    }
                }
                if (!Schema::hasTable('metrics_counter_submission_institution_daily')) {
                    Schema::create('metrics_counter_submission_institution_daily', function (Blueprint $table) use ($contextTable, $contextIdColumn) {
                        $table->string('load_id', 255);
                        $table->bigInteger('context_id');
                        $table->bigInteger('submission_id');
                        $table->bigInteger('institution_id');
                        $table->date('date');
                        $table->integer('metric_investigations');
                        $table->integer('metric_investigations_unique');
                        $table->integer('metric_requests');
                        $table->integer('metric_requests_unique');
                        $table->foreign('context_id')->references($contextIdColumn)->on($contextTable);
                        $table->foreign('submission_id')->references('submission_id')->on('submissions');
                        $table->foreign('institution_id')->references('institution_id')->on('institutions');
                        $table->index(['load_id'], 'metrics_counter_submission_institution_daily_load_id');
                        $table->index(['context_id', 'submission_id'], 'metrics_counter_submission_institution_daily_context_id_submission_id');
                    });
                    if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
                        DB::statement('ALTER TABLE metrics_counter_submission_institution_daily ADD CONSTRAINT uc_load_id_context_id_submission_id_institution_id_date UNIQUE INCLUDE (load_id, context_id, submission_id, institution_id, date)');
                    } else {
                        DB::statement('ALTER TABLE metrics_counter_submission_institution_daily ADD CONSTRAINT uc_load_id_context_id_submission_id_institution_id_date UNIQUE (load_id, context_id, submission_id, institution_id, date)');
                    }
                }
                if (!Schema::hasTable('metrics_counter_submission_institution_monthly')) {
                    Schema::create('metrics_counter_submission_institution_monthly', function (Blueprint $table) use ($contextTable, $contextIdColumn) {
                        $table->bigInteger('context_id');
                        $table->bigInteger('submission_id');
                        $table->bigInteger('institution_id');
                        $table->string('month', 6);
                        $table->integer('metric_investigations');
                        $table->integer('metric_investigations_unique');
                        $table->integer('metric_requests');
                        $table->integer('metric_requests_unique');
                        $table->foreign('context_id')->references($contextIdColumn)->on($contextTable);
                        $table->foreign('submission_id')->references('submission_id')->on('submissions');
                        $table->foreign('institution_id')->references('institution_id')->on('institutions');
                        $table->index(['context_id', 'submission_id'], 'metrics_counter_submission_institution_monthly_context_id_submission_id');
                    });
                    if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
                        DB::statement('ALTER TABLE metrics_counter_submission_institution_monthly ADD CONSTRAINT uc_context_id_submission_id_institution_id_month UNIQUE INCLUDE (context_id, submission_id, institution_id, month)');
                    } else {
                        DB::statement('ALTER TABLE metrics_counter_submission_institution_monthly ADD CONSTRAINT uc_context_id_submission_id_institution_id_month UNIQUE (context_id, submission_id, institution_id, month)');
                    }
                }
                if (!Schema::hasTable('metrics_counter_submission_geo_daily')) {
                    Schema::create('metrics_counter_submission_geo_daily', function (Blueprint $table) use ($contextTable, $contextIdColumn) {
                        $table->string('load_id', 255);
                        $table->bigInteger('context_id');
                        $table->bigInteger('submission_id');
                        $table->string('country', 2)->default('');
                        $table->string('region', 3)->default('');
                        $table->string('city', 255)->default('');
                        $table->date('date');
                        $table->integer('metric_investigations');
                        $table->integer('metric_investigations_unique');
                        $table->integer('metric_requests');
                        $table->integer('metric_requests_unique');
                        $table->foreign('context_id')->references($contextIdColumn)->on($contextTable);
                        $table->foreign('submission_id')->references('submission_id')->on('submissions');
                        $table->index(['load_id'], 'metrics_counter_submission_geo_daily_load_id');
                        $table->index(['context_id', 'submission_id'], 'metrics_counter_submission_geo_daily_context_id_submission_id');
                    });
                    if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
                        DB::statement('ALTER TABLE metrics_counter_submission_geo_daily ADD CONSTRAINT uc_load_id_context_id_submission_id_country_region_city_date UNIQUE INCLUDE (load_id, context_id, submission_id, country, region, city, date)');
                    } else {
                        DB::statement('ALTER TABLE metrics_counter_submission_geo_daily ADD CONSTRAINT uc_load_id_context_id_submission_id_country_region_city_date UNIQUE (load_id, context_id, submission_id, country, region, city, date)');
                    }
                }
                if (!Schema::hasTable('metrics_counter_submission_geo_monthly')) {
                    Schema::create('metrics_counter_submission_geo_monthly', function (Blueprint $table) use ($contextTable, $contextIdColumn) {
                        $table->bigInteger('context_id');
                        $table->bigInteger('submission_id');
                        $table->string('country', 2)->default('');
                        $table->string('region', 3)->default('');
                        $table->string('city', 255)->default('');
                        $table->string('month', 6);
                        $table->integer('metric_investigations');
                        $table->integer('metric_investigations_unique');
                        $table->integer('metric_requests');
                        $table->integer('metric_requests_unique');
                        $table->foreign('context_id')->references($contextIdColumn)->on($contextTable);
                        $table->foreign('submission_id')->references('submission_id')->on('submissions');
                        $table->index(['context_id', 'submission_id'], 'metrics_counter_submission_geo_monthly_context_id_submission_id');
                    });
                    if (substr(Config::getVar('database', 'driver'), 0, strlen('postgres')) === 'postgres') {
                        DB::statement('ALTER TABLE metrics_counter_submission_geo_monthly ADD CONSTRAINT uc_context_id_submission_id_country_region_city_month UNIQUE INCLUDE (context_id, submission_id, country, region, city, month)');
                    } else {
                        DB::statement('ALTER TABLE metrics_counter_submission_geo_monthly ADD CONSTRAINT uc_context_id_submission_id_country_region_city_month UNIQUE (context_id, submission_id, country, region, city, month)');
                    }
                }

                // Usage stats total temporary records
                Schema::create('usage_stats_total_temporary_records', function (Blueprint $table) use ($contextTable, $contextIdColumn) {
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

                // Usage stats unique item investigations temporary records
                Schema::create('usage_stats_unique_investigations_temporary_records', function (Blueprint $table) {
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

                // Usage stats unique item requests temporary records
                Schema::create('usage_stats_unique_requests_temporary_records', function (Blueprint $table) {
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

                // Usage stats institution temporary records
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
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('metrics');
        /*
        Schema::drop('metrics_context');
        Schema::drop('metrics_issue');
        Schema::drop('metrics_submission');
        Schema::drop('metrics_counter_submission_daily');
        Schema::drop('metrics_counter_submission_monthly');
        Schema::drop('metrics_counter_submission_institution_daily');
        Schema::drop('metrics_counter_submission_institution_monthly');
        Schema::drop('metrics_counter_submission_geo_daily');
        Schema::drop('metrics_counter_submission_geo_monthly');
        Schema::drop('usage_stats_total_temporary_records');
        Schema::drop('usage_stats_unique_investigations_temporary_records');
        Schema::drop('usage_stats_unique_requests_temporary_records');
        Schema::drop('usage_stats_institution_temporary_records');
        */
    }
}
