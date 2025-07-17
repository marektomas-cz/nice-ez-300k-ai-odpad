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
        Schema::create('script_execution_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('script_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('executed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('execution_context')->nullable();
            $table->enum('status', ['pending', 'running', 'success', 'failed', 'timeout', 'cancelled'])
                  ->default('pending');
            $table->longText('output')->nullable();
            $table->text('error_message')->nullable();
            $table->decimal('execution_time', 10, 6)->nullable(); // in seconds
            $table->bigInteger('memory_usage')->nullable(); // in bytes
            $table->enum('trigger_type', ['manual', 'event', 'scheduled', 'api', 'webhook'])
                  ->default('manual');
            $table->json('trigger_data')->nullable();
            $table->datetime('started_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->json('resource_usage')->nullable();
            $table->json('security_flags')->nullable();
            $table->timestamps();

            // Indexes for performance and analytics
            $table->index(['script_id', 'created_at']);
            $table->index(['client_id', 'created_at']);
            $table->index(['executed_by']);
            $table->index(['status']);
            $table->index(['trigger_type']);
            $table->index(['started_at']);
            $table->index(['completed_at']);
            $table->index(['execution_time']);
            
            // Composite indexes for common queries
            $table->index(['script_id', 'status', 'created_at']);
            $table->index(['client_id', 'status', 'created_at']);
            $table->index(['script_id', 'trigger_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('script_execution_logs');
    }
};