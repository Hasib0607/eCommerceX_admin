<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('designlists', function (Blueprint $table) {
            // Check if 'title_bg' exists before renaming it to avoid errors
            if (Schema::hasColumn('designlists', 'title_bg')) {
                DB::statement('ALTER TABLE designlists CHANGE title_bg button_color VARCHAR(50)');
            }

            // Add 'button' column only if it doesn't already exist
            if (!Schema::hasColumn('designlists', 'button')) {
                $table->string('button', 100)->nullable()->after('title_color');
                $table->string('image_description', 50)->nullable()->after('button');
            }
        });

        Schema::table('templates', function (Blueprint $table) {
            // Add 'blog' and 'contact' columns only if they don't already exist
            if (!Schema::hasColumn('templates', 'blog')) {
                $table->string('blog', 50)->nullable()->after('offer');
            }

            if (!Schema::hasColumn('templates', 'contact')) {
                $table->string('contact', 50)->nullable()->after('blog');
            }
        });

        Schema::table('designs', function (Blueprint $table) {
            // Add 'blog' and 'contact' columns only if they don't already exist
            if (!Schema::hasColumn('designs', 'blog')) {
                $table->string('blog', 50)->nullable()->after('offer');
            }

            if (!Schema::hasColumn('designs', 'contact')) {
                $table->string('contact', 50)->nullable()->after('blog');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('designlists', function (Blueprint $table) {
            // Drop 'button' column only if it exists
            if (Schema::hasColumn('designlists', 'button')) {
                $table->dropColumn('button');
                $table->dropColumn('image_description');
            }

            // Check if 'button_color' exists before renaming it back to 'title_bg'
            if (Schema::hasColumn('designlists', 'button_color')) {
                DB::statement('ALTER TABLE designlists CHANGE button_color title_bg VARCHAR(255)');
            }
        });

        Schema::table('templates', function (Blueprint $table) {
            // Drop 'blog' and 'contact' columns only if they exist
            if (Schema::hasColumn('templates', 'blog')) {
                $table->dropColumn('blog');
            }

            if (Schema::hasColumn('templates', 'contact')) {
                $table->dropColumn('contact');
            }
        });

        Schema::table('designs', function (Blueprint $table) {
            // Drop 'blog' and 'contact' columns only if they exist
            if (Schema::hasColumn('designs', 'blog')) {
                $table->dropColumn('blog');
            }

            if (Schema::hasColumn('designs', 'contact')) {
                $table->dropColumn('contact');
            }
        });
    }
};
