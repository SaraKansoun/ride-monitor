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
        Schema::create('incident_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewed_by')->constrained('users')->cascadeOnDelete();
            $table->string('fault_decision')->index();
            $table->text('notes');
            $table->timestamp('reviewed_at')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('deactivated_at')->nullable()->index();
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['incident_id', 'is_active']);
            $table->index(['reviewed_by', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incident_reviews');
    }
};
