<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Messenger\BaleProvider;
use App\Services\Messenger\EitaaProvider;
use App\Services\Messenger\MessengerManager;
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

        $this->app->singleton(MessengerManager::class, function ($app): MessengerManager {
            return new MessengerManager([
                'bale' => new BaleProvider(
                    botToken: (string) config('services.bale.bot_token', ''),
                    botUsername: (string) config('services.bale.bot_username', ''),
                ),
                'eitaa' => new EitaaProvider(
                    botToken: (string) config('services.eitaa.bot_token', ''),
                    botUsername: (string) config('services.eitaa.bot_username', ''),
                ),
            ]);
        });
    }

    public function boot(): void
    {
        //
    }
}
