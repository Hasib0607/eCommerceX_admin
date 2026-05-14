<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\AdminVisitor;

class AdminVisitorFactory extends Factory
{
    protected $model = AdminVisitor::class;

    public function definition()
    {
        return [
            'store_id' => $this->faker->optional()->randomNumber(),
            'store_url' => $this->faker->optional()->url(),
            'user_id' => $this->faker->optional()->randomNumber(),
            'page_url' => $this->faker->url(),
            'page_title' => $this->faker->sentence(6),
            'refer_page_url' => $this->faker->optional()->url(),
            'ip' => $this->faker->ipv4(),
            'device' => $this->faker->randomElement(['Desktop', 'Mobile', 'Tablet']),
            'mac' => $this->faker->macAddress(),
            'os' => $this->faker->randomElement(['Windows', 'macOS', 'Linux', 'Android', 'iOS']),
            'browser' => $this->faker->randomElement(['Chrome', 'Firefox', 'Edge', 'Safari', 'Opera']),
            'country_code' => $this->faker->countryCode(),
            'country_name' => $this->faker->country(),
            'state' => $this->faker->state(),
            'city' => $this->faker->city(),
            'zip_code' => $this->faker->postcode(),
            'location' => $this->faker->latitude() . ',' . $this->faker->longitude(),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'category_id' => $this->faker->optional()->randomNumber(),
            'product_id' => $this->faker->optional()->randomNumber(),
            'visit_time' => $this->faker->time('H:i:s'),
            'time_zone' => $this->faker->timezone(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
