<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            // "Starters", "Main Course", "Dessert", "Special Offers"…
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            // Rendered in the template's separate "Special Offers" block.
            $table->boolean('is_special_offers')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['restaurant_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_sections');
    }
};
