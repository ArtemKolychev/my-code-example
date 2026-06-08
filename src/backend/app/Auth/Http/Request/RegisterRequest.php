<?php

declare(strict_types=1);

namespace App\Auth\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @implements TypedRequest<RegisterVO>
 */
final class RegisterRequest extends FormRequest implements TypedRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function getBody(): RegisterVO
    {
        return new RegisterVO(
            name: $this->string('name')->value(),
            email: $this->string('email')->value(),
            password: $this->string('password')->value(),
        );
    }
}
