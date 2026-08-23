<?php

use App\Models\User;
use App\Services\ShoppingCartService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var list<string> $arguments */
$arguments = $_SERVER['argv'];
[, $userId, $sessionId, $readyFile, $barrierFile] = $arguments;
$user = User::findOrFail($userId);

touch($readyFile);

while (! file_exists($barrierFile)) {
    usleep(10_000);
}

app(ShoppingCartService::class)->mergeGuestCartIntoUserCart($user, $sessionId);
