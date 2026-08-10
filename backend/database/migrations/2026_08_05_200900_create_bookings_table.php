<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            // Human-friendly code shown on the confirmation page, e.g. "FG-8K3QP2".
            $table->string('reference', 16)->unique();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            // Nullable: guests may book without an account.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('guest_name');
            $table->string('guest_email');
            $table->string('guest_phone', 40)->nullable();

            $table->date('booking_date');
            $table->time('booking_time');
            $table->unsignedTinyInteger('party_size');
            $table->text('notes')->nullable();
            $table->unsignedTinyInteger('discount_percent')->nullable();

            $table->enum('status', ['pending', 'confirmed', 'seated', 'completed', 'cancelled'])
                ->default('pending');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            /*
             * Not a UNIQUE index: a cancelled booking keeps its row, and the
             * guest must be able to re-book the same slot afterwards. The
             * "one live booking per slot" rule is enforced in BookingController,
             * which can exclude cancelled rows the way an index cannot.
             */
            $table->index(
                ['restaurant_id', 'guest_email', 'booking_date', 'booking_time'],
                'bookings_slot_lookup'
            );
            $table->index(['restaurant_id', 'booking_date']);
            $table->index(['user_id', 'booking_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
