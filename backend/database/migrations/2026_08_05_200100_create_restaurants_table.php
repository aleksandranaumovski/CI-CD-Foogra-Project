<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();

            $table->string('address');
            $table->string('city')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 2)->default('GB');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            $table->decimal('average_price', 8, 2)->default(0);
            // Drives the "-30%" ribbon in the template.
            $table->unsignedTinyInteger('discount_percent')->nullable();

            $table->string('hero_image_path')->nullable();
            $table->string('thumbnail_path')->nullable();

            // e.g. ["Wifi", "Parking", "Wheelchair Accessible"] and ["Visa", "Amex"].
            $table->json('services')->nullable();
            $table->json('payment_methods')->nullable();
            $table->json('social_links')->nullable();

            $table->boolean('is_featured')->default(false);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable();

            /*
             * Denormalised aggregates. Recomputing AVG()/COUNT() over reviews on
             * every listing request is the single easiest way to make this page
             * slow, so the Review model keeps these in sync on write instead.
             */
            $table->decimal('rating_avg', 3, 1)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->unsignedInteger('bookings_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'is_featured']);
            $table->index(['status', 'rating_avg']);
            $table->index(['status', 'average_price']);
            $table->index(['category_id', 'status']);
            $table->index('owner_id');
            $table->fullText(['name', 'description', 'address', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
