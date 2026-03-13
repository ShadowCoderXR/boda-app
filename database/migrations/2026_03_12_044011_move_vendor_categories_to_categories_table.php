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
        // 1. Move existing data
        $vendors = DB::table('vendors')->get();
        foreach ($vendors as $vendor) {
            if (! empty($vendor->category)) {
                $category = DB::table('categories')->updateOrInsert(
                    ['name' => $vendor->category, 'type' => 'vendor', 'user_id' => $vendor->user_id],
                    ['color' => 'stone', 'created_at' => now(), 'updated_at' => now()]
                );

                $categoryId = DB::table('categories')
                    ->where('name', $vendor->category)
                    ->where('type', 'vendor')
                    ->where('user_id', $vendor->user_id)
                    ->value('id');

                DB::table('category_vendor')->insert([
                    'category_id' => $categoryId,
                    'vendor_id' => $vendor->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 2. Drop legacy column
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('vendors', 'category')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->string('category')->nullable()->after('name');
            });
        }

        // Optional: reconstruct category strings from pivot (best effort)
        $pivotData = DB::table('category_vendor')
            ->join('categories', 'category_vendor.category_id', '=', 'categories.id')
            ->select('vendor_id', 'categories.name')
            ->get();

        foreach ($pivotData as $data) {
            DB::table('vendors')->where('id', $data->vendor_id)->update(['category' => $data->name]);
        }
    }
};
