<?php

declare(strict_types=1);

namespace App\Auth\Http\Resource;

use App\Auth\Application\DTO\Response\TokenPairDto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property TokenPairDto $resource
 */
final class TokenPairResource extends JsonResource
{
    /** @return array{access_token: string, refresh_token: string, expires_in: int, token_type: string} */
    public function toArray(Request $request): array
    {
        return [
            'access_token' => $this->resource->accessToken,
            'refresh_token' => $this->resource->refreshToken,
            'expires_in' => $this->resource->expiresIn,
            'token_type' => 'Bearer',
        ];
    }
}
