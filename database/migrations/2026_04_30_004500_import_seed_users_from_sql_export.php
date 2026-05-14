<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $columns = Schema::getColumnListing('users');

        $users = [
            [
                'id' => 1,
                'currency_id' => 1,
                'name' => 'Ebitans Supper Admin',
                'image' => null,
                'social_img' => null,
                'email' => 'hasib.soyad@gmail.com',
                'email_verified_at' => null,
                'password' => '$2y$10$WPjQ98/IkIWvItoqCzcJ8O7sSvTiQYB9zYHGXQpmFWFQn7Iv/wPGW',
                'remember_token' => 'Ui2BBlmRXPI043DsKgXJarDs6KUBNqv8s3AHnu9VtSniZdlnrHUx5WCCF2i7',
                'created_at' => '2022-04-04 09:22:17',
                'updated_at' => '2025-10-09 14:06:54',
                'type' => 'superadmin',
                'phone' => '01677515574',
                'role_id' => null,
                'username' => null,
                'otp' => 'NULL',
                'store_id' => null,
                'address' => null,
                'customer_id' => null,
                'active_cpanel' => null,
                'domain' => null,
                'cpanel_username' => null,
                'cpanel_password' => null,
                'offertime' => null,
                'auth_type' => 'phone',
                'google_id' => null,
                'facebook_id' => null,
                'comment' => '',
                'comment_date' => null,
                'seller_id' => null,
                'referral' => '1687670912Ovno',
                'refer_by' => null,
                'referral_commission' => 10,
                'total_commission' => 0,
                'register_from' => null,
                'paid_registration' => 0,
            ],
            [
                'id' => 15,
                'currency_id' => 1,
                'name' => 'Hasib Ahmed',
                'image' => null,
                'social_img' => null,
                'email' => 'hasib.soyad@gmail.com',
                'email_verified_at' => null,
                'password' => '$2y$10$Uo0mfFzEP5mhHyZ4qjbruO4HcRYVGaIlkNfb6L7Dz1bHFmfEPQzwa',
                'remember_token' => 'kNEMaNP8xpQNd4YqeKZsmm5XvZ9K7M6NVzPJM5IHY9ePfK5YX6KgplTJvSvR',
                'created_at' => '2022-05-11 10:34:52',
                'updated_at' => '2025-10-09 13:59:42',
                'type' => 'admin',
                'phone' => '01677515579',
                'role_id' => null,
                'username' => null,
                'otp' => 'NULL',
                'store_id' => null,
                'address' => null,
                'customer_id' => null,
                'active_cpanel' => 'active',
                'domain' => 'gadgetghor.ebitans.com',
                'cpanel_username' => null,
                'cpanel_password' => null,
                'offertime' => null,
                'auth_type' => 'phone',
                'google_id' => null,
                'facebook_id' => null,
                'comment' => 'boss',
                'comment_date' => null,
                'seller_id' => null,
                'referral' => '1687670912SsAn',
                'refer_by' => null,
                'referral_commission' => 10,
                'total_commission' => 6818.8,
                'register_from' => null,
                'paid_registration' => 0,
            ],
        ];

        foreach ($users as $user) {
            $payload = array_intersect_key($user, array_flip($columns));
            DB::table('users')->updateOrInsert(['id' => $user['id']], $payload);
        }

        $maxId = max(array_column($users, 'id'));
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE users AUTO_INCREMENT = ' . ($maxId + 1));
        } elseif ($driver === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('users', 'id'), GREATEST((SELECT MAX(id) FROM users), {$maxId}))");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        DB::table('users')->whereIn('id', [1, 15])->delete();
    }
};
