<?php

declare(strict_types=1);

namespace App\Auth\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @implements TypedRequest<RefreshVO>
 */
final class RefreshRequest extends FormRequest implements TypedRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'refresh_token' => ['required', 'string'],
        ];
    }

    public function getBody(): RefreshVO
    {
        return new RefreshVO(
            refreshToken: $this->string('refresh_token')->value(),
        );
    }
}
