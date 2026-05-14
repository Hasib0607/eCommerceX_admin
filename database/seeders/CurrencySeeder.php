<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('currencies')->insert([
            [
                'country' => 'Bangladesh',
                'code' => "BDT",
                'symbol' => "৳",
                'rate' => "119.7300",
            ],
            [
                'country' => 'United States Of America',
                'code' => "USD",
                'symbol' => "$",
                'rate' => "1.0000",
            ],
            [
                'country' => 'European Union',
                'code' => "EUR",
                'symbol' => "€",
                'rate' => "0.9000",
            ],
            [
                'country' => 'Republic of China',
                'code' => "CNY",
                'symbol' => "¥",
                'rate' => "7.0200",
            ],
        ]);
    }
}
