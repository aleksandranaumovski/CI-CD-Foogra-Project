<?php

namespace Database\Seeders;

use App\Enums\RestaurantStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Dish;
use App\Models\MenuSection;
use App\Models\OpeningHour;
use App\Models\Restaurant;
use App\Models\RestaurantImage;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Recreates every restaurant that appears in the Foogra mockups — same names,
 * images, prices and discount ribbons — then pads the set out so the listing
 * page has enough rows for filters, sorting and pagination to be meaningful.
 */
class RestaurantSeeder extends Seeder
{
    /**
     * name, category slug, thumbnail, target score, discount, avg price, address, city, postcode
     */
    public const SHOWCASE = [
        ['Pizzeria da Alfredo', 'pizza-italian', 'location_1.jpg', 8.9, 30, 24, '27 Old Gloucester St', 'London', 'WC1N 3AX'],
        ['Best Burghers', 'burghers', 'location_2.jpg', 9.5, 40, 14, '27 Old Gloucester St', 'London', 'WC1N 3AX'],
        ['Vego Life', 'vegetarian', 'location_3.jpg', 7.5, null, 21, '8 Patriot Square', 'London', 'E2 9NF'],
        ['Sushi Temple', 'japanese-sushi', 'location_4.jpg', 9.5, 25, 12, '22 Hertsmere Rd', 'London', 'E14 4ED'],
        ['Auto Pizza', 'pizza-italian', 'location_5.jpg', 7.0, 30, 25, '135 Newtownards Road', 'Belfast', 'BT4 1AB'],
        ['Alliance Grill', 'burghers', 'location_6.jpg', 8.9, 15, 18, 'Old Shire Ln', 'Waltham Abbey', 'EN9 3RX'],
        ['Alliance Wok', 'chinese', 'location_7.jpg', 8.9, 30, 25, '27 Old Gloucester St', 'London', 'WC1N 3AX'],
        ['Dragon Tower', 'japanese-sushi', 'location_8.jpg', 8.9, null, 28, '22 Hertsmere Rd', 'London', 'E14 4ED'],
        ['El Paso Tacos', 'mexican', 'location_9.jpg', 8.9, null, 29, '97845 Baker St', 'London', 'W1U 6TW'],
        ['Monnalisa Bakery', 'bakery', 'location_10.jpg', 8.9, null, 29, '27 Old Gloucester St', 'London', 'WC1N 3AX'],
        ['Guachamole', 'mexican', 'location_11.jpg', 8.9, null, 29, '135 Newtownards Road', 'Belfast', 'BT4 1AB'],
        ['Pechino Express', 'chinese', 'location_12.jpg', 8.9, null, 29, '27 Old Gloucester St', 'London', 'WC1N 3AX'],
        ['La Monnalisa', 'pizza-italian', 'location_list_1.jpg', 9.5, 30, 35, '8 Patriot Square', 'London', 'E2 9NF'],
        ['Alliance Cantina', 'mexican', 'location_list_2.jpg', 8.0, 40, 30, '27 Old Gloucester St', 'London', 'WC1N 3AX'],
        ['Sushi Gold', 'japanese-sushi', 'location_list_3.jpg', 9.0, 25, 20, 'Old Shire Ln', 'Waltham Abbey', 'EN9 3RX'],
        ['Mr. Pepper', 'vegetarian', 'location_list_4.jpg', 9.5, 30, 20, '27 Old Gloucester St', 'London', 'WC1N 3AX'],
        ['Dragon Tower Express', 'chinese', 'location_list_5.jpg', 8.0, 50, 35, '22 Hertsmere Rd', 'London', 'E14 4ED'],
        ['Bella Napoli', 'pizza-italian', 'location_list_6.jpg', 8.5, 45, 25, '135 Newtownards Road', 'Belfast', 'BT4 1AB'],
    ];

    /** Menu lifted from `detail-restaurant.html`, priced per section. */
    private const MENU = [
        ['name' => 'Starters', 'special' => false, 'dishes' => [
            ['Imported Salmon Steak', 9.90, 'Base de arroz, aguacate, salmón noruego, semillas de sésamo, edamame, wakame y soja light', false],
            ['Poke Bowl', 7.90, 'Queso de cabra light, dátiles, jamón serrano y rúcula', false],
            ['Ensalada Cesar', 8.90, 'Lechuga, tomate, espinacas, pollo asado, picatostes, queso proteínico y salsa césar 0%', false],
            ['Burrata di Andria', 6.50, 'Creamy burrata, heritage tomatoes, basil oil and sourdough toast', true],
        ]],
        ['name' => 'Main Course', 'special' => false, 'dishes' => [
            ['Oriental', 15.90, 'Cama de tabule con taquitos de pollo a la mostaza light', false],
            ['Vegan Burger', 11.90, 'Medio pollo asado acompañado de arroz o patatas al toque masala', true],
            ['Indio Fit', 10.90, 'Lechuga, tomate, espinacas, pollo asado, picatostes, queso proteínico y salsa césar 0%', false],
            ['Truffle Tagliatelle', 17.50, 'Fresh egg pasta, black truffle, aged parmesan and brown butter', true],
        ]],
        ['name' => 'Dessert', 'special' => false, 'dishes' => [
            ['Tiramisu della Casa', 6.90, 'Savoiardi soaked in espresso, mascarpone cream and cocoa', true],
            ['Panna Cotta', 5.90, 'Vanilla panna cotta with a wild berry compote', true],
            ['Lemon Sorbet', 4.50, 'Sicilian lemon sorbet served with a splash of prosecco', true],
        ]],
        ['name' => 'Special Offers', 'special' => true, 'dishes' => [
            ['Two-Course Lunch', 14.90, 'Any starter plus any main course, served Monday to Friday until 3pm', false],
            ['Chef\'s Tasting Menu', 29.90, 'Five courses chosen by the kitchen, with an optional wine pairing', false],
            ['Family Sharing Platter', 24.90, 'A generous platter built for four, with sides and dips included', false],
        ]],
    ];

    public function run(): void
    {
        $categories = Category::pluck('id', 'slug');
        $owners = User::where('role', UserRole::Owner)->pluck('id')->all();

        foreach (self::SHOWCASE as $index => [$name, $categorySlug, $image, $score, $discount, $price, $address, $city, $postcode]) {
            $restaurant = Restaurant::updateOrCreate(
                ['slug' => str($name)->slug()->value()],
                [
                    'owner_id' => $owners[$index % max(count($owners), 1)] ?? null,
                    'category_id' => $categories[$categorySlug],
                    'name' => $name,
                    'tagline' => 'Book a table at the best price',
                    'description' => $this->description($name),
                    'address' => $address,
                    'city' => $city,
                    'postal_code' => $postcode,
                    'country' => 'GB',
                    'latitude' => round(51.5074 + (mt_rand(-600, 600) / 10000), 7),
                    'longitude' => round(-0.1278 + (mt_rand(-900, 900) / 10000), 7),
                    'phone' => '+44 20 '.mt_rand(1000, 9999).' '.mt_rand(1000, 9999),
                    'email' => str($name)->slug()->value().'@foogra.test',
                    'website' => 'https://'.str($name)->slug()->value().'.example.com',
                    'average_price' => $price,
                    'discount_percent' => $discount,
                    'hero_image_path' => 'img/restaurant_detail_hero.jpg',
                    'thumbnail_path' => "img/{$image}",
                    'services' => ['Wifi', 'Parking', 'Wheelchair Accessible'],
                    'payment_methods' => ['Mastercard', 'Visa', 'Amex'],
                    'social_links' => [
                        'facebook' => 'https://facebook.com/'.str($name)->slug()->value(),
                        'instagram' => 'https://instagram.com/'.str($name)->slug()->value(),
                        'twitter' => 'https://x.com/'.str($name)->slug()->value(),
                    ],
                    'is_featured' => $index < 6,
                    'status' => RestaurantStatus::Published,
                    'published_at' => now()->subDays(180 - ($index * 7)),
                ]
            );

            // ReviewSeeder reads the target score back out of SHOWCASE by slug.
            $this->attachGallery($restaurant);
            $this->attachOpeningHours($restaurant);
            $this->attachMenu($restaurant);
        }

        $this->seedFillerRestaurants($owners);
    }

    private function description(string $name): string
    {
        return "{$name} has been serving its neighbourhood for over a decade. The kitchen "
            ."works with small local suppliers, changes the menu with the seasons, and keeps "
            ."a short, well-chosen wine list to match.\n\n"
            .'The dining room seats forty across two floors, with a handful of pavement tables '
            .'when the weather allows. Booking ahead is recommended at weekends.';
    }

    /*
     * The three helpers below use bulk inserts rather than one Model::create()
     * per row. Across ~65 restaurants that is the difference between roughly
     * 2,400 individual INSERTs and a couple of dozen batched ones.
     */

    private function attachGallery(Restaurant $restaurant): void
    {
        if ($restaurant->images()->exists()) {
            return;
        }

        $now = now();

        DB::table((new RestaurantImage)->getTable())->insert(
            collect(range(1, 5))->map(fn (int $n) => [
                'restaurant_id' => $restaurant->id,
                'path' => "img/detail_gallery/detail_{$n}.jpg",
                'thumbnail_path' => "img/thumb_detail_{$n}.jpg",
                'caption' => "{$restaurant->name} — photo {$n}",
                'sort_order' => $n,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }

    /** Lunch 11:00–15:00 and dinner 18:00–01:00, Monday to Saturday. Closed Sundays. */
    private function attachOpeningHours(Restaurant $restaurant): void
    {
        if ($restaurant->openingHours()->exists()) {
            return;
        }

        $now = now();
        $rows = [];

        foreach (range(0, 6) as $day) {
            $closed = $day === 0;

            foreach ([['lunch', '11:00:00', '15:00:00'], ['dinner', '18:00:00', '01:00:00']] as [$service, $open, $close]) {
                $rows[] = [
                    'restaurant_id' => $restaurant->id,
                    'day_of_week' => $day,
                    'service' => $service,
                    'opens_at' => $closed ? null : $open,
                    'closes_at' => $closed ? null : $close,
                    'is_closed' => $closed,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table((new OpeningHour)->getTable())->insert($rows);
    }

    private function attachMenu(Restaurant $restaurant): void
    {
        if ($restaurant->menuSections()->exists()) {
            return;
        }

        $now = now();
        $dishRows = [];

        foreach (self::MENU as $order => $section) {
            // Sections are created individually because the dishes need their ids.
            $menuSection = MenuSection::create([
                'restaurant_id' => $restaurant->id,
                'name' => $section['name'],
                'sort_order' => $section['special'] ? 99 : $order,
                'is_special_offers' => $section['special'],
            ]);

            foreach ($section['dishes'] as $position => [$dish, $price, $body, $vegetarian]) {
                $dishRows[] = [
                    'restaurant_id' => $restaurant->id,
                    'menu_section_id' => $menuSection->id,
                    'name' => $dish,
                    'description' => $body,
                    'price' => $price,
                    'is_vegetarian' => $vegetarian,
                    'is_available' => true,
                    'sort_order' => $position,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table((new Dish)->getTable())->insert($dishRows);
    }

    /**
     * Extra published restaurants (plus a couple of drafts) so the listing page
     * paginates and the admin dashboard has non-published rows to manage.
     */
    private function seedFillerRestaurants(array $owners): void
    {
        $categoryIds = Category::pluck('id')->all();

        Restaurant::factory()
            ->count(42)
            ->sequence(fn ($sequence) => [
                'category_id' => $categoryIds[$sequence->index % count($categoryIds)],
                'owner_id' => $owners[$sequence->index % max(count($owners), 1)] ?? null,
            ])
            ->create()
            ->each(function (Restaurant $restaurant): void {
                $this->attachGallery($restaurant);
                $this->attachOpeningHours($restaurant);
                $this->attachMenu($restaurant);
            });

        Restaurant::factory()->count(4)->draft()->create([
            'owner_id' => $owners[0] ?? null,
            'category_id' => $categoryIds[0],
        ]);
    }
}
