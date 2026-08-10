<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->text('body');

            // Foogra scores everything out of 10, in half-point steps.
            $table->decimal('rating_food', 3, 1);
            $table->decimal('rating_service', 3, 1);
            $table->decimal('rating_location', 3, 1);
            $table->decimal('rating_price', 3, 1);
            // Mean of the four above; stored so we can index and sort on it.
            $table->decimal('rating_overall', 3, 1)->default(0);

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('unhelpful_count')->default(0);
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // One review per person per restaurant.
            $table->unique(['restaurant_id', 'user_id']);
            $table->index(['restaurant_id', 'status', 'published_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
