<?php

declare(strict_types=1);

namespace App\Auth\Http\Action;

use App\Auth\Application\Command\Refresh\RefreshCommand;
use App\Auth\Application\Handler\Refresh\RefreshHandler;
use App\Auth\Http\Request\RefreshRequest;
use App\Auth\Http\Resource\TokenPairResource;
use Illuminate\Http\Resources\Json\JsonResource;

final class RefreshAction
{
    public function __construct(
        private readonly RefreshHandler $handler,
    ) {}

    public function __invoke(RefreshRequest $request): JsonResource
    {
        $body = $request->getBody();

        $tokenPair = ($this->handler)(new RefreshCommand(
            refreshToken: $body->refreshToken,
        ));

        return new TokenPairResource($tokenPair);
    }
}
