<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Repository;

use App\Auth\Application\Contract\UserQueryInterface;
use App\Auth\Domain\User\User;
use App\Auth\Domain\User\ValueObject\Email;
use App\Auth\Domain\User\ValueObject\HashedPassword;
use App\Auth\Domain\User\ValueObject\Name;
use App\Auth\Domain\User\ValueObject\UserId;
use App\Auth\Infrastructure\Model\UserModel;
use Illuminate\Support\Facades\Log;

final class UserQueryRepository implements UserQueryInterface
{
    public function findByEmail(Email $email): ?User
    {
        Log::debug('repository.user.findByEmail', ['email' => $email->value]);
        $model = UserModel::query()->where('email', $email->value)->first();

        return $model !== null ? $this->toUser($model) : null;
    }

    public function existsByEmail(Email $email): bool
    {
        Log::debug('repository.user.existsByEmail', ['email' => $email->value]);

        return UserModel::query()->where('email', $email->value)->exists();
    }

    private function toUser(UserModel $model): User
    {
        return User::reconstitute(
            id: UserId::fromString($model->id),
            email: Email::fromString($model->email),
            hashedPassword: HashedPassword::fromHash($model->password),
            name: Name::fromString($model->name),
        );
    }
}
