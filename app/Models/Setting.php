<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';
    protected $keyType    = 'string';
    public $incrementing  = false;

    protected $fillable = ['key', 'value'];

    public static function get(string $key, string $default = ''): string
    {
        return static::find($key)?->value ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function getBool(string $key, bool $default = true): bool
    {
        $row = static::find($key);
        return $row === null ? $default : $row->value === '1';
    }

    public static function setBool(string $key, bool $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value ? '1' : '0']);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $val = static::find($key)?->value;
        return $val === null ? $default : (int) $val;
    }
}
