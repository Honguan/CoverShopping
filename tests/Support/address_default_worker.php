<?php

use App\Models\Address;
use App\Models\User;
use App\Services\AddressBookService;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var list<string> $arguments */
$arguments = $_SERVER['argv'];
[, $userId, $addressId, $readyFile, $barrierFile] = $arguments;

touch($readyFile);

while (! file_exists($barrierFile)) {
    usleep(10_000);
}

app(AddressBookService::class)->setDefaultAddress(
    User::findOrFail($userId),
    Address::findOrFail($addressId),
);
