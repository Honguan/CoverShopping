<?php

use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\OrderPaymentService;
use App\Services\ReturnRequestService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var list<string> $arguments */
$arguments = $_SERVER['argv'];
[, $adminId, $returnRequestId, $endpoint, $readyFile, $barrierFile] = $arguments;
$admin = User::findOrFail($adminId);
$returnRequest = ReturnRequest::findOrFail($returnRequestId);
$order = $returnRequest->order;

touch($readyFile);

while (! file_exists($barrierFile)) {
    usleep(10_000);
}

try {
    if ($endpoint === 'payment') {
        app(OrderPaymentService::class)->transition($admin, $order, 'refunded');
    } else {
        app(ReturnRequestService::class)->updateStatus($admin, $returnRequest, 'refunded');
    }
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}
