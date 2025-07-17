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
        Schema::create('script_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('script_id')->constrained()->onDelete('cascade');
            $table->string('version_number', 50)->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->longText('code');
            $table->string('language', 50)->default('javascript');
            $table->json('configuration')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('change_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('checksum', 64)->index();
            $table->integer('size_bytes')->default(0);
            $table->json('performance_metrics')->nullable();
            $table->json('security_scan_results')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['script_id', 'version_number']);
            $table->index(['script_id', 'created_at']);
            $table->index('checksum');
            $table->unique(['script_id', 'version_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('script_versions');
    }
};