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
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id(); 
            $table->foreignId('user_id')
            ->constrained('users')
            ->cascadeOnDelete()
            ->cascadeOnUpdate(); 
            $table->string('action_type');
            $table->text('description'); 
            $table->string('ip_address', 45)->nullable(); 
            $table->timestamp('created_at')->useCurrent(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};