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
        Schema::create('client_secrets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('key', 255)->index();
            $table->longText('encrypted_value');
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_used_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['client_id', 'key']);
            $table->index(['client_id', 'is_active']);
            $table->index(['expires_at']);
            $table->index(['last_used_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_secrets');
    }
};