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
        Schema::create('product_variants', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')
    ->constrained('products')
    ->cascadeOnDelete()
    ->cascadeOnUpdate();
    $table->string('size')->nullable();
    $table->string('color')->nullable();
    $table->decimal('cost_price', 10, 2)->nullable()->default(0.00);
    $table->decimal('selling_price', 10, 2)->nullable()->default(0.00); 
    $table->string('variant_name')->nullable(); 
    $table->string('sku')->unique()->nullable();
    $table->string('images')->nullable();
    $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};