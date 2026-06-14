<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Pattern codes for referral (ارجاع) and membership-approval (عضویت) SMS.
        $defaults = [
            'ippanel_referral_pattern_code'       => '',
            'ippanel_referral_pattern_variable'   => 'message',
            'ippanel_membership_pattern_code'     => '',
            'ippanel_membership_pattern_variable' => 'name',
        ];

        foreach ($defaults as $key => $value) {
            DB::table('settings')->insertOrIgnore(['key' => $key, 'value' => $value]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'ippanel_referral_pattern_code',
            'ippanel_referral_pattern_variable',
            'ippanel_membership_pattern_code',
            'ippanel_membership_pattern_variable',
        ])->delete();
    }
};
