<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Setting;
use App\Services\Messenger\BaleProvider;
use App\Services\Messenger\EitaaProvider;
use App\Services\Messenger\MessengerManager;
use App\Services\SMS\IPPanelSmsService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // IPPanel: DB settings override .env, with .env as fallback.
        // Resolved lazily so the DB is available when the service is first used.
        $this->app->singleton(IPPanelSmsService::class, function (): IPPanelSmsService {
            $dbKey    = Setting::get('ippanel_api_key', '');
            $dbSender = Setting::get('ippanel_sender', '');

            $dbPatternCode     = Setting::get('ippanel_otp_pattern_code', '');
            $dbPatternVariable = Setting::get('ippanel_otp_pattern_variable', '');

            return new IPPanelSmsService(
                apiKey:          $dbKey    ?: (string) config('services.ippanel.api_key', ''),
                patternCode:     $dbPatternCode     ?: (string) config('services.ippanel.pattern_code', ''),
                sender:          $dbSender ?: (string) config('services.ippanel.sender', ''),
                patternVariable: $dbPatternVariable ?: (string) config('services.ippanel.pattern_variable', 'code'),
            );
        });

        $this->app->singleton(MessengerManager::class, function (): MessengerManager {
            return new MessengerManager([
                'bale'  => new BaleProvider(
                    botToken:    (string) config('services.bale.bot_token', ''),
                    botUsername: (string) config('services.bale.bot_username', ''),
                ),
                'eitaa' => new EitaaProvider(
                    botToken:    (string) config('services.eitaa.bot_token', ''),
                    botUsername: (string) config('services.eitaa.bot_username', ''),
                ),
            ]);
        });
    }

    public function boot(): void {}
}
