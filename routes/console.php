<?php

use App\Models\Setting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$generateDay = (! Schema::hasTable('settings'))
    ? 1
    : max(1, min(28, (int) Setting::get('langganan_generate_day', 1)));

Schedule::command('subscription:bill')
    ->when(function (): bool {
        if (! Schema::hasTable('settings')) {
            return false;
        }

        return (bool) Setting::get('langganan_auto_generate', false);
    })
    ->monthlyOn($generateDay, '00:05');

$retailGenerateDay = (! Schema::hasTable('settings'))
    ? 1
    : max(1, min(28, (int) Setting::get('retail_generate_day', 1)));

Schedule::command('retail:bill')
    ->when(function (): bool {
        if (! Schema::hasTable('settings')) {
            return false;
        }

        return (bool) Setting::get('retail_auto_generate', false);
    })
    ->monthlyOn($retailGenerateDay, '00:10');
