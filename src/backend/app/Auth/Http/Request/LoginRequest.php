<?php

declare(strict_types=1);

namespace App\Auth\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @implements TypedRequest<LoginVO>
 */
final class LoginRequest extends FormRequest implements TypedRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function getBody(): LoginVO
    {
        return new LoginVO(
            email: $this->string('email')->value(),
            password: $this->string('password')->value(),
        );
    }
}
