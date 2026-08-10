<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            // 0 = Sunday … 6 = Saturday, matching Carbon's dayOfWeek.
            $table->unsignedTinyInteger('day_of_week');
            $table->enum('service', ['lunch', 'dinner']);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(['restaurant_id', 'day_of_week', 'service'], 'opening_hours_unique_slot');
            $table->index(['restaurant_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_hours');
    }
};
