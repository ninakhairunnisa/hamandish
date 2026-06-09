<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\SMS\IPPanelSmsService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IPPanelSmsService::class, function (): IPPanelSmsService {
            return new IPPanelSmsService(
                apiKey: config('services.ippanel.api_key', ''),
                patternCode: config('services.ippanel.pattern_code', ''),
                sender: config('services.ippanel.sender', ''),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
