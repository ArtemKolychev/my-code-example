<?php

declare(strict_types=1);

namespace App\Auth\Http\Action;

use App\Auth\Application\Command\Login\LoginCommand;
use App\Auth\Application\Handler\Login\LoginHandler;
use App\Auth\Http\Request\LoginRequest;
use App\Auth\Http\Resource\TokenPairResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Ramsey\Uuid\Uuid;

final readonly class LoginAction
{
    public function __construct(
        private LoginHandler $handler,
    ) {}

    /**
     * @throws \Throwable
     */
    public function __invoke(LoginRequest $request): JsonResource
    {
        $body = $request->getBody();

        $tokenPair = ($this->handler)(new LoginCommand(
            jobId: Uuid::uuid4()->toString(),
            email: $body->email,
            password: $body->password,
        ));

        return new TokenPairResource($tokenPair);
    }
}
