<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dishes', function (Blueprint $table) {
            $table->id();
            // Denormalised restaurant_id so ownership checks never need a join.
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_section_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 8, 2);
            $table->string('image_path')->nullable();
            $table->json('allergens')->nullable();
            $table->boolean('is_vegetarian')->default(false);
            $table->boolean('is_available')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['menu_section_id', 'sort_order']);
            $table->index(['restaurant_id', 'is_available']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dishes');
    }
};
