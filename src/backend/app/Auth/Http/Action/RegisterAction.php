<?php

declare(strict_types=1);

namespace App\Auth\Http\Action;

use App\Auth\Application\Command\RegisterUser\RegisterUserCommand;
use App\Auth\Application\Handler\RegisterUser\RegisterUserHandler;
use App\Auth\Http\Request\RegisterRequest;
use Illuminate\Http\JsonResponse;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

final class RegisterAction
{
    public function __construct(
        private readonly RegisterUserHandler $handler,
    ) {}

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $body = $request->getBody();
        $jobId = Uuid::uuid4()->toString();

        ($this->handler)(new RegisterUserCommand(
            jobId: $jobId,
            email: $body->email,
            password: $body->password,
            name: $body->name,
        ));

        return response()->json(['jobId' => $jobId], Response::HTTP_ACCEPTED);
    }
}
