<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['users', 'drivers', 'vehicles'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->boolean('is_active')->default(true)->after('status')->index();
                $table->timestamp('deactivated_at')->nullable()->after('is_active')->index();
                $table->foreignId('deactivated_by')->nullable()->after('deactivated_at')->constrained('users')->nullOnDelete();
            });
        }

        DB::table('users')->where('status', '!=', 'active')->update([
            'is_active' => false,
            'deactivated_at' => now(),
        ]);

        DB::table('drivers')->where('status', '!=', 'active')->update([
            'is_active' => false,
            'deactivated_at' => now(),
        ]);

        DB::table('vehicles')->where('status', '!=', 'active')->update([
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['vehicles', 'drivers', 'users'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropForeign("{$tableName}_deactivated_by_foreign");
                $table->dropColumn(['is_active', 'deactivated_at', 'deactivated_by']);
            });
        }
    }
};
