<?php

/**
 * @file classes/migration/MetricsMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MetricsMigration
 * @brief Describe database table structures.
 */

namespace PKP\migration;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MetricsMigration extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // OLAP statistics data table.
        /*
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
        */

        Schema::create('metrics_context', function (Blueprint $table) {
            $table->string('load_id', 255);
            $table->bigInteger('context_id');
            $table->dateTime('date', $precision = 0);
            $table->integer('metric');
            $table->index(['load_id'], 'metrics_context_load_id');
        });
        Schema::create('metrics_issue', function (Blueprint $table) {
            $table->string('load_id', 255);
            $table->bigInteger('context_id');
            $table->bigInteger('issue_id');
            $table->bigInteger('issue_galley_id')->nullable();
            $table->dateTime('date', $precision = 0);
            $table->integer('metric');
            $table->index(['load_id'], 'metrics_issue_load_id');
        });
        Schema::create('metrics_submission', function (Blueprint $table) {
            $table->string('load_id', 255);
            $table->bigInteger('context_id');
            $table->bigInteger('submission_id');
            $table->bigInteger('representation_id')->nullable();
            $table->bigInteger('file_id')->nullable();
            $table->smallInteger('file_type')->nullable();
            $table->dateTime('date', $precision = 0);
            $table->integer('metric');
            $table->index(['load_id'], 'metrics_submission_load_id');
        });
        Schema::create('metrics_counter_submission_daily', function (Blueprint $table) {
            $table->string('load_id', 255);
            $table->bigInteger('context_id');
            $table->bigInteger('submission_id');
            $table->dateTime('date', $precision = 0);
            $table->integer('metric_investigations');
            $table->integer('metric_investigations_unique');
            $table->integer('metric_requests');
            $table->integer('metric_requests_unique');
            $table->index(['load_id'], 'metrics_counter_submission_daily_load_id');
        });
        Schema::create('metrics_counter_submission_monthly', function (Blueprint $table) {
            $table->bigInteger('context_id');
            $table->bigInteger('submission_id');
            $table->string('month', 6);
            $table->integer('metric_investigations');
            $table->integer('metric_investigations_unique');
            $table->integer('metric_requests');
            $table->integer('metric_requests_unique');
        });
        Schema::create('metrics_counter_submission_institution_daily', function (Blueprint $table) {
            $table->string('load_id', 255);
            $table->bigInteger('context_id');
            $table->bigInteger('submission_id');
            $table->bigInteger('institution_id');
            $table->dateTime('date', $precision = 0);
            $table->integer('metric_investigations');
            $table->integer('metric_investigations_unique');
            $table->integer('metric_requests');
            $table->integer('metric_requests_unique');
            $table->index(['load_id'], 'metrics_counter_submission_institution_daily_load_id');
        });
        Schema::create('metrics_counter_submission_institution_monthly', function (Blueprint $table) {
            $table->bigInteger('context_id');
            $table->bigInteger('submission_id');
            $table->bigInteger('institution_id');
            $table->string('month', 6);
            $table->integer('metric_investigations');
            $table->integer('metric_investigations_unique');
            $table->integer('metric_requests');
            $table->integer('metric_requests_unique');
        });
        Schema::create('metrics_counter_submission_geo_daily', function (Blueprint $table) {
            $table->string('load_id', 255);
            $table->bigInteger('context_id');
            $table->bigInteger('submission_id');
            $table->string('country', 2)->nullable();
            $table->string('region', 3)->nullable();
            $table->string('city', 255)->nullable();
            $table->dateTime('date', $precision = 0);
            $table->integer('metric_investigations');
            $table->integer('metric_investigations_unique');
            $table->integer('metric_requests');
            $table->integer('metric_requests_unique');
            $table->index(['load_id'], 'metrics_counter_submission_geo_daily_load_id');
        });
        Schema::create('metrics_counter_submission_geo_monthly', function (Blueprint $table) {
            $table->bigInteger('context_id');
            $table->bigInteger('submission_id');
            $table->string('country', 2)->nullable();
            $table->string('region', 3)->nullable();
            $table->string('city', 255)->nullable();
            $table->string('month', 6);
            $table->integer('metric_investigations');
            $table->integer('metric_investigations_unique');
            $table->integer('metric_requests');
            $table->integer('metric_requests_unique');
        });

        // Usage stats total temporary records
        Schema::create('usage_stats_total_temporary_records', function (Blueprint $table) {
            $table->dateTime('date', $precision = 0);
            $table->bigInteger('line_number');
            $table->string('canonical_url', 255);
            $table->bigInteger('issue_id')->nullable();
            $table->bigInteger('context_id');
            $table->bigInteger('submission_id')->nullable();
            $table->bigInteger('representation_id')->nullable();
            $table->bigInteger('assoc_type');
            $table->bigInteger('assoc_id');
            $table->smallInteger('file_type')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('region', 3)->nullable();
            $table->string('city', 255)->nullable();
            $table->string('institution_ids', 255);
            $table->string('load_id', 255);
        });

        // Usage stats unique temporary records
        Schema::create('usage_stats_unique_temporary_records', function (Blueprint $table) {
            $table->dateTime('date', $precision = 0);
            $table->bigInteger('line_number');
            $table->bigInteger('issue_id')->nullable();
            $table->bigInteger('context_id');
            $table->bigInteger('submission_id');
            $table->bigInteger('representation_id')->nullable();
            $table->bigInteger('assoc_type');
            $table->bigInteger('assoc_id');
            $table->smallInteger('file_type')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('region', 3)->nullable();
            $table->string('city', 255)->nullable();
            $table->string('institution_ids', 255);
            $table->string('load_id', 255);
        });
    }

    /**
     * Reverse the migration.
     */
    public function down()
    {
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
        Schema::drop('usage_stats_unique_temporary_records');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\migration\MetricsMigration', '\MetricsMigration');
}
