<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('incident_media', function (Blueprint $table) {
            $table->string('sha256_hash', 64)->nullable()->after('size')->index();
        });

        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->string('media_fingerprint', 64)->nullable()->after('incident_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->dropIndex(['media_fingerprint']);
            $table->dropColumn('media_fingerprint');
        });

        Schema::table('incident_media', function (Blueprint $table) {
            $table->dropIndex(['sha256_hash']);
            $table->dropColumn('sha256_hash');
        });
    }
};
