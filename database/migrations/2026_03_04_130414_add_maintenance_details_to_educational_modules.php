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
        Schema::table('educational_modules', function (Blueprint $table) {
            $table->boolean('is_maintenance_guide')->default(false)->after('category');
            $table->string('watering_info')->nullable()->after('is_maintenance_guide');
            $table->string('light_info')->nullable()->after('watering_info');
            $table->string('humidity_info')->nullable()->after('light_info');
            $table->enum('difficulty', ['Mudah', 'Sedang', 'Sulit'])->nullable()->after('humidity_info');
            $table->json('maintenance_steps_json')->nullable()->after('content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('educational_modules', function (Blueprint $table) {
            $table->dropColumn(['is_maintenance_guide', 'watering_info', 'light_info', 'humidity_info', 'difficulty', 'maintenance_steps_json']);
        });
    }
};
