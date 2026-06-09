<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^09\d{9}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'شماره موبایل باید با فرمت ایرانی وارد شود (09XXXXXXXXX).',
        ];
    }
}
