<?php

use App\Services\AiStatusOverview;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Generates the AI status overview ahead of time, so the first visitor after a
// deploy doesn't wait for the AI. docker/production/start.sh runs it at startup.
Artisan::command('ai-overview:warm', function (AiStatusOverview $overview) {
    if (! $overview->isEnabled()) {
        $this->info('AI status overview is off; nothing to do.');

        return;
    }

    try {
        $result = $overview->get();
        $result = $result['outdated'] ? $overview->refresh() : $result;
        $this->info("AI status overview ready (generated {$result['generated_at']}).");
    } catch (Throwable $exception) {
        $this->warn('AI status overview could not be generated: '.$exception->getMessage());
    }
})->purpose('Generate the AI status overview ahead of time');
