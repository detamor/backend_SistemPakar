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
        Schema::create('certainty_factor_levels', function (Blueprint $table) {
            $table->id();
            $table->string('label')->unique(); // Tidak Yakin, Sedikit Yakin, dll
            $table->decimal('value', 3, 1)->unique(); // 0, 0.4, 0.6, 0.8, 1
            $table->integer('order')->default(0); // Urutan tampilan
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certainty_factor_levels');
    }
};
