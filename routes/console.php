<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('about:covershopping', function () {
    $this->info('CoverShopping modern commerce application.');
});
