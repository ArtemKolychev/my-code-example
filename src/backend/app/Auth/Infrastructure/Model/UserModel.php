<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Model;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $email
 * @property string $password
 * @property string $name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class UserModel extends Model
{
    protected $table = 'auth.users';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['id', 'email', 'password', 'name'];
}
