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
        Schema::create('ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->text('summary')->nullable();
            $table->text('detected_events')->nullable();
            $table->decimal('confidence_score', 3, 2)->nullable();
            $table->text('recommendation')->nullable();
            $table->json('raw_response')->nullable();
            $table->string('status')->default('pending')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('deactivated_at')->nullable()->index();
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['incident_id', 'is_active']);
            $table->index(['status', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_analyses');
    }
};
