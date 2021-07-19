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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UsageStatsTemp extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
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
            $table->string('country', 2)->nullable();
            $table->string('region', 3)->nullable();
            $table->string('city', 255)->nullable();
            $table->string('institution_ids', 255);
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
            $table->string('country', 2)->nullable();
            $table->string('region', 3)->nullable();
            $table->string('city', 255)->nullable();
            $table->string('institution_ids', 255);
            $table->string('load_id', 255);
        });
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
