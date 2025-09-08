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
        Schema::create('aqi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('region_id')->constrained('regions')->onDelete('cascade');
            $table->timestamp('observed_at')->index();
            $table->string('dominent_pol');
            $table->decimal('dew', 5, 2)->nullable();
            $table->decimal('h', 5, 2)->nullable();
            $table->decimal('p', 8, 2)->nullable();
            $table->decimal('pm10', 5, 2)->nullable();
            $table->decimal('pm25', 5, 2)->nullable();
            $table->decimal('r', 5, 2)->nullable();
            $table->decimal('t', 5, 2)->nullable();
            $table->decimal('w', 5, 2)->nullable();
            $table->decimal('wg', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iaqi');
    }
};
