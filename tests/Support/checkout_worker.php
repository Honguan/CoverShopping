<?php

use App\Models\User;
use App\Services\OrderCheckoutService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var list<string> $arguments */
$arguments = $_SERVER['argv'];
[, $userId, $couponCode, $readyFile, $barrierFile] = $arguments;
$user = User::findOrFail($userId);

touch($readyFile);

while (! file_exists($barrierFile)) {
    usleep(10_000);
}

try {
    app(OrderCheckoutService::class)->createOrderFromCart($user, couponCode: $couponCode === '-' ? null : $couponCode);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}
