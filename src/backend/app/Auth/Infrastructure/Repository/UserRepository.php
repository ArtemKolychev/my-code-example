<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Repository;

use App\Auth\Domain\User\Repository\UserRepositoryInterface;
use App\Auth\Domain\User\User;
use App\Auth\Domain\User\ValueObject\Email;
use App\Auth\Domain\User\ValueObject\HashedPassword;
use App\Auth\Domain\User\ValueObject\Name;
use App\Auth\Domain\User\ValueObject\UserId;
use App\Auth\Infrastructure\Model\UserModel;
use Illuminate\Support\Facades\Log;

final class UserRepository implements UserRepositoryInterface
{
    public function findById(UserId $id): ?User
    {
        Log::debug('repository.user.findById', ['id' => $id->value]);
        $model = UserModel::query()->find($id->value);

        return $model !== null ? $this->toUser($model) : null;
    }

    public function save(User $user): void
    {
        Log::debug('repository.user.save', ['id' => $user->id()->value]);

        UserModel::query()->upsert(
            [
                'id' => $user->id()->value,
                'email' => $user->email()->value,
                'password' => $user->hashedPassword()->hash,
                'name' => $user->name()->value,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            uniqueBy: ['id'],
            update: ['email', 'password', 'name', 'updated_at'],
        );
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
