<?php

use App\Models\Order;
use App\Models\User;
use App\Services\ReturnRequestService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var list<string> $arguments */
$arguments = $_SERVER['argv'];
[, $userId, $orderId, $readyFile, $barrierFile] = $arguments;
$user = User::findOrFail($userId);
$order = Order::findOrFail($orderId);

touch($readyFile);

while (! file_exists($barrierFile)) {
    usleep(10_000);
}

try {
    app(ReturnRequestService::class)->request($user, $order, 'Concurrent return');
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}
