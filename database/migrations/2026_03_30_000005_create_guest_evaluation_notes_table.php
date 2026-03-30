<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('guest_evaluation_notes')) {
            return;
        }

        Schema::create('guest_evaluation_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plant_id')->constrained('plants')->cascadeOnDelete();
            $table->foreignId('disease_id')->nullable()->constrained('diseases')->nullOnDelete();
            $table->decimal('certainty_value', 6, 4)->default(0.0000);
            $table->text('user_notes');
            $table->json('selected_symptoms_json')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_evaluation_notes');
    }
};

