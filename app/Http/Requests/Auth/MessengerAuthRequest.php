<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class MessengerAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider'  => ['required', 'string', 'in:bale,eitaa'],
            'init_data' => ['required', 'string'],
        ];
    }
}
