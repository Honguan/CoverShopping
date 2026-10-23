<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('shopping:prune-guest-data')->daily()->onOneServer();

Artisan::command('about:covershopping', function () {
    $this->info('CoverShopping modern commerce application.');
});
