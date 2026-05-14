<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderStatus extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('order_statuses')->insert([
            [
                'name' => 'Pending',
                'name_bn' => "বিচারাধীন",
                'slug' => "Pending",
                'slug_edit' => "0",
            ],
            [
                'name' => 'On Hold',
                'name_bn' => "স্হগিত",
                'slug' => "On Hold",
                'slug_edit' => "0",
            ],
            [
                'name' => 'Payment Success',
                'name_bn' => "পেমেন্ট সফল",
                'slug' => "Payment Success",
                'slug_edit' => "0",
            ],
            [
                'name' => 'Payment Failed',
                'name_bn' => "পেমেন্ট ব্যর্থ হয়েছে",
                'slug' => "Payment Failed",
                'slug_edit' => "0",
            ],
            [
                'name' => 'Processing',
                'name_bn' => "প্রক্রিয়াকরণ",
                'slug' => "Processing",
                'slug_edit' => "0",
            ],
            [
                'name' => 'Shipping',
                'name_bn' => "পাঠানো",
                'slug' => "Shipping",
                'slug_edit' => "0",
            ],
            [
                'name' => 'Delivered',
                'name_bn' => "বিতরণ করা হয়েছে",
                'slug' => "Delivered",
                'slug_edit' => "0",
            ],
            [
                'name' => 'Returned',
                'name_bn' => "ফিরে এসেছে",
                'slug' => "Returned",
                'slug_edit' => "0",
            ],
            [
                'name' => 'Cancelled',
                'name_bn' => "বাতিল",
                'slug' => "Cancelled",
                'slug_edit' => "0",
            ],
            [
                'name' => 'Booked',
                'name_bn' => "বুকিং",
                'slug' => "Booked",
                'slug_edit' => "0",
            ],
        ]);
    }
}
