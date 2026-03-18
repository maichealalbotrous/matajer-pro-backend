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
        Schema::create('subscription_keys', function (Blueprint $table) {
            $table->id();
         
        $table->string('license_key')->unique(); 
        $table->integer('duration_months'); 
        $table->date('activated_at')->nullable();
        $table->date('deactivated_at')->nullable();
        $table->boolean('is_used')->default(false);
        $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete()->cascadeOnUpdate(); 
        $table->timestamps();
        });
    }

    /*
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_keys');
    }
};