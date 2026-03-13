<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\InspirationItem;
use App\Models\User;
use App\Models\Category;

$user = User::first() ?: User::factory()->create();
auth()->login($user);

$catName = "Debug Category " . time();
$cat = Category::create([
    'name' => $catName,
    'type' => 'inspiration',
    'user_id' => $user->id,
    'color' => 'sage'
]);

echo "Created Category: ID={$cat->id}, Name={$cat->name}\n";

$item = InspirationItem::create([
    'type' => 'color',
    'category_id' => $cat->id,
    'content' => 'test-content',
    'user_id' => $user->id
]);

echo "Created Item: ID={$item->id}, raw category_id={$item->category_id}\n";

$freshItem = InspirationItem::find($item->id);
echo "Fresh Item: ID={$freshItem->id}, category_id in DB=" . $freshItem->getAttributes()['category_id'] . "\n";

$relatedCat = $freshItem->category;
if ($relatedCat) {
    echo "Related Category found: ID={$relatedCat->id}, Name={$relatedCat->name}\n";
} else {
    echo "Related Category is NULL!\n";
    
    // Check if category exists manually
    $exists = Category::find($freshItem->getAttributes()['category_id']);
    echo "Manual search for category by ID " . $freshItem->getAttributes()['category_id'] . ": " . ($exists ? "FOUND: " . $exists->name : "NOT FOUND") . "\n";
}
