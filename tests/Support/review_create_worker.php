<?php

use App\Models\Product;
use App\Models\User;
use App\Services\ProductReviewService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var list<string> $arguments */
$arguments = $_SERVER['argv'];
[, $userId, $productId, $orderItemId, $rating, $content, $readyFile, $barrierFile] = $arguments;
$user = User::findOrFail($userId);
$product = Product::findOrFail($productId);

touch($readyFile);

while (! file_exists($barrierFile)) {
    usleep(10_000);
}

try {
    $review = app(ProductReviewService::class)->create($user, $product, (int) $orderItemId, (int) $rating, $content);
    echo json_encode([$review->rating, $review->content], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}
