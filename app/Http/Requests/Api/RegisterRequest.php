<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => 'required|string|min:3|max:50|unique:users,name',
            'password' => 'required|string|min:4',
            'avatar' => ['nullable', 'string', Rule::in(array_keys(config('game.avatars', [])))],
        ];
    }
}
