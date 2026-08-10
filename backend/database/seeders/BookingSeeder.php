<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Like ReviewSeeder, this batches its inserts: the Booking model refreshes the
 * restaurant's cached `bookings_count` on every create, which is right for the
 * API but would triple the query count here. The counter is set once per
 * restaurant at the end instead.
 */
class BookingSeeder extends Seeder
{
    private const TIMES = [
        '12:00:00', '12:30:00', '13:00:00', '13:30:00',
        '20:00:00', '20:30:00', '21:00:00', '21:30:00',
    ];

    public function run(): void
    {
        $customers = User::where('role', UserRole::Customer)->get();
        $demoCustomer = User::where('email', 'customer@foogra.test')->first();
        $restaurants = Restaurant::published()->get();

        if ($restaurants->isEmpty() || $customers->isEmpty()) {
            return;
        }

        $now = now();
        $rows = [];
        $references = [];

        foreach ($restaurants as $restaurant) {
            foreach (range(1, mt_rand(2, 6)) as $ignored) {
                $customer = $customers->random();
                $status = $this->status();

                $rows[] = [
                    'reference' => $this->uniqueReference($references),
                    'restaurant_id' => $restaurant->id,
                    'user_id' => $customer->id,
                    'guest_name' => $customer->name,
                    'guest_email' => $customer->email,
                    'guest_phone' => $customer->phone,
                    'booking_date' => $now->copy()->addDays(mt_rand(-45, 45))->toDateString(),
                    'booking_time' => self::TIMES[array_rand(self::TIMES)],
                    'party_size' => mt_rand(1, 8),
                    'notes' => mt_rand(1, 4) === 1 ? 'Window table if possible, please.' : null,
                    'discount_percent' => $restaurant->discount_percent,
                    'status' => $status->value,
                    // Every row must carry an identical key set: a bulk insert
                    // builds one column list for the whole batch.
                    'confirmed_at' => $status === BookingStatus::Pending ? null : $now,
                    'cancelled_at' => $status === BookingStatus::Cancelled ? $now : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Guarantee the demo customer has something to look at in "My bookings".
        if ($demoCustomer) {
            foreach ($restaurants->random(min(4, $restaurants->count()))->values() as $index => $restaurant) {
                $rows[] = [
                    'reference' => $this->uniqueReference($references),
                    'restaurant_id' => $restaurant->id,
                    'user_id' => $demoCustomer->id,
                    'guest_name' => $demoCustomer->name,
                    'guest_email' => $demoCustomer->email,
                    'guest_phone' => $demoCustomer->phone,
                    'booking_date' => $now->copy()->addDays(3 + $index)->toDateString(),
                    'booking_time' => '20:00:00',
                    'party_size' => 2 + $index,
                    'notes' => null,
                    'discount_percent' => $restaurant->discount_percent,
                    'status' => BookingStatus::Confirmed->value,
                    'confirmed_at' => $now,
                    'cancelled_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                Wishlist::firstOrCreate([
                    'user_id' => $demoCustomer->id,
                    'restaurant_id' => $restaurant->id,
                ]);
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table((new Booking)->getTable())->insert($chunk);
        }

        // A scattering of wishlist entries across the rest of the user base.
        $wishlistRows = [];

        foreach ($customers->random(min(20, $customers->count())) as $customer) {
            foreach ($restaurants->random(min(3, $restaurants->count())) as $restaurant) {
                $wishlistRows["{$customer->id}-{$restaurant->id}"] = [
                    'user_id' => $customer->id,
                    'restaurant_id' => $restaurant->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Keyed above so a repeated (user, restaurant) pair cannot break the
        // unique index; and skip any pair the demo customer already claimed.
        $existing = Wishlist::pluck('restaurant_id', 'user_id');

        $wishlistRows = collect($wishlistRows)
            ->reject(fn (array $row) => ($existing[$row['user_id']] ?? null) === $row['restaurant_id'])
            ->values()
            ->all();

        if ($wishlistRows !== []) {
            DB::table((new Wishlist)->getTable())->insertOrIgnore($wishlistRows);
        }

        $this->refreshBookingCounts();
    }

    /** @param  array<string, true>  $seen */
    private function uniqueReference(array &$seen): string
    {
        do {
            $reference = 'FG-'.str()->upper(str()->random(6));
        } while (isset($seen[$reference]));

        $seen[$reference] = true;

        return $reference;
    }

    private function refreshBookingCounts(): void
    {
        DB::statement('
            UPDATE restaurants r
            SET bookings_count = (
                SELECT COUNT(*) FROM bookings b
                WHERE b.restaurant_id = r.id AND b.deleted_at IS NULL
            )
        ');
    }

    private function status(): BookingStatus
    {
        return match (mt_rand(1, 10)) {
            1, 2 => BookingStatus::Pending,
            3 => BookingStatus::Cancelled,
            4, 5 => BookingStatus::Completed,
            default => BookingStatus::Confirmed,
        };
    }
}
