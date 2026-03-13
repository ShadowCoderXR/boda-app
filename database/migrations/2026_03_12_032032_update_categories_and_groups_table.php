<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('type')->default('guest')->after('id');
            $table->boolean('is_default')->default(false)->after('color');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('parent_id');
        });

        Schema::table('inspiration_items', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('type')->constrained('categories')->onDelete('set null');
        });

        // We will keep the 'category' string in inspiration_items for now to avoid losing data,
        // but it will be deprecated.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inspiration_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['type', 'is_default']);
        });
    }
};
