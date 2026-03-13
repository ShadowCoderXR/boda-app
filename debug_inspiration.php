<?php
use App\Models\InspirationItem;
use App\Models\User;
use App\Models\Category;
use Illuminate\Support\Str;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = User::first() ?: User::factory()->create();
auth()->login($user);

$actual = "Decoración";
echo "Testing with: $actual\n";
echo "Is UUID? " . (Str::isUuid($actual) ? "Yes" : "No") . "\n";

if (!empty($actual) && !Str::isUuid($actual)) {
    echo "Creating category...\n";
    $newCat = Category::create([
        'name' => $actual,
        'type' => 'inspiration',
        'user_id' => auth()->id(),
        'color' => 'sage'
    ]);
    echo "Created Category ID: " . $newCat->id . "\n";
    $actual = $newCat->id;
}

$item = InspirationItem::create([
    'type' => 'color',
    'category_id' => $actual,
    'content' => 'test',
    'user_id' => auth()->id()
]);

echo "InspirationItem Category ID: " . $item->category_id . "\n";
$loaded = $item->fresh()->category;
echo "Loaded Category: " . ($loaded ? $loaded->name : "NULL") . "\n";
