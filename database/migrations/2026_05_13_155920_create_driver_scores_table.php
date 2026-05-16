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
        Schema::create('driver_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->default(100)->index();
            $table->unsignedInteger('total_incidents')->default(0);
            $table->unsignedInteger('unsafe_events')->default(0);
            $table->timestamp('last_updated_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('deactivated_at')->nullable()->index();
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_scores');
    }
};
