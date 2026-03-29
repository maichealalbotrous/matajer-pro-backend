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
        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')
            ->constrained('branches')
            ->cascadeOnDelete()
            ->cascadeOnUpdate();;
            $table->foreignId('invoice_id')->unique()
            ->constrained('invoices')
            ->cascadeOnDelete()
            ->cascadeOnUpdate();
            $table->decimal('gross_profit', 15, 2);
            $table->decimal('net_profit', 15, 2);   
            $table->foreignId('approved_by')
            ->constrained('users')
            ->cascadeOnDelete()
            ->cascadeOnUpdate();; 
            $table->timestamp('settlement_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settlements');
    }
};