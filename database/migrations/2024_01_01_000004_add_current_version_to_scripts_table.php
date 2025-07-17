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
        Schema::table('scripts', function (Blueprint $table) {
            $table->foreignId('current_version_id')->nullable()->constrained('script_versions')->nullOnDelete();
            $table->index('current_version_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scripts', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
            $table->dropIndex(['current_version_id']);
            $table->dropColumn('current_version_id');
        });
    }
};