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
        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->string('suggested_fault_decision')->nullable()->after('recommendation')->index();
            $table->decimal('fault_confidence_score', 3, 2)->nullable()->after('suggested_fault_decision');
            $table->text('fault_reasoning')->nullable()->after('fault_confidence_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->dropColumn([
                'suggested_fault_decision',
                'fault_confidence_score',
                'fault_reasoning',
            ]);
        });
    }
};
