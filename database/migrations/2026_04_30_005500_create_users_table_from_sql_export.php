<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureUsersTable();

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

        $availableColumns = array_flip(Schema::getColumnListing('users'));

        foreach ($users as $user) {
            $id = $user['id'];
            $payload = array_intersect_key($user, $availableColumns);

            DB::table('users')->updateOrInsert(['id' => $id], $payload);
        }

        DB::statement('ALTER TABLE users AUTO_INCREMENT = 45106');
    }

    private function ensureUsersTable(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $this->addUsersColumns($table);
            });

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $this->addUsersColumns($table, true);
        });
    }

    private function addUsersColumns(Blueprint $table, bool $onlyMissing = false): void
    {
        $add = static function (string $column, callable $callback) use ($onlyMissing, $table): void {
            if (!$onlyMissing || !Schema::hasColumn('users', $column)) {
                $callback($table);
            }
        };

        $add('id', fn (Blueprint $table) => $table->id());
        $add('currency_id', fn (Blueprint $table) => $table->unsignedBigInteger('currency_id')->default(1)->index());
        $add('name', fn (Blueprint $table) => $table->string('name', 191)->nullable()->index());
        $add('image', fn (Blueprint $table) => $table->text('image')->nullable());
        $add('social_img', fn (Blueprint $table) => $table->text('social_img')->nullable());
        $add('email', fn (Blueprint $table) => $table->string('email', 191)->nullable()->index());
        $add('email_verified_at', fn (Blueprint $table) => $table->timestamp('email_verified_at')->nullable());
        $add('password', fn (Blueprint $table) => $table->string('password', 191)->nullable());
        $add('remember_token', fn (Blueprint $table) => $table->rememberToken());
        $add('created_at', fn (Blueprint $table) => $table->timestamp('created_at')->nullable());
        $add('updated_at', fn (Blueprint $table) => $table->timestamp('updated_at')->nullable());
        $add('type', fn (Blueprint $table) => $table->string('type', 191)->nullable());
        $add('phone', fn (Blueprint $table) => $table->string('phone', 191)->nullable()->index());
        $add('role_id', fn (Blueprint $table) => $table->string('role_id', 191)->nullable());
        $add('username', fn (Blueprint $table) => $table->string('username', 191)->nullable());
        $add('otp', fn (Blueprint $table) => $table->string('otp', 191)->nullable());
        $add('store_id', fn (Blueprint $table) => $table->string('store_id')->nullable());
        $add('address', fn (Blueprint $table) => $table->string('address')->nullable());
        $add('customer_id', fn (Blueprint $table) => $table->string('customer_id')->nullable());
        $add('active_cpanel', fn (Blueprint $table) => $table->string('active_cpanel')->nullable());
        $add('domain', fn (Blueprint $table) => $table->string('domain')->nullable());
        $add('cpanel_username', fn (Blueprint $table) => $table->string('cpanel_username')->nullable());
        $add('cpanel_password', fn (Blueprint $table) => $table->string('cpanel_password')->nullable());
        $add('offertime', fn (Blueprint $table) => $table->timestamp('offertime')->nullable());
        $add('auth_type', fn (Blueprint $table) => $table->string('auth_type')->default('phone'));
        $add('google_id', fn (Blueprint $table) => $table->string('google_id')->nullable());
        $add('facebook_id', fn (Blueprint $table) => $table->string('facebook_id')->nullable());
        $add('comment', fn (Blueprint $table) => $table->text('comment')->nullable());
        $add('comment_date', fn (Blueprint $table) => $table->dateTime('comment_date')->nullable());
        $add('seller_id', fn (Blueprint $table) => $table->unsignedBigInteger('seller_id')->nullable());
        $add('referral', fn (Blueprint $table) => $table->string('referral')->nullable());
        $add('refer_by', fn (Blueprint $table) => $table->string('refer_by')->nullable());
        $add('referral_commission', fn (Blueprint $table) => $table->double('referral_commission')->default(10));
        $add('total_commission', fn (Blueprint $table) => $table->double('total_commission')->default(0));
        $add('register_from', fn (Blueprint $table) => $table->string('register_from', 191)->nullable());
        $add('paid_registration', fn (Blueprint $table) => $table->tinyInteger('paid_registration')->default(0));
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            DB::table('users')->whereIn('id', [1, 15])->delete();
        }
    }
};
