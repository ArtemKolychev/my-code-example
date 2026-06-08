<?php

declare(strict_types=1);

namespace App\Auth\Tests\Fixtures;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class AuthTestSeeder extends Seeder
{
    public const string ALICE_ID = '01960000-0000-0000-0000-000000000001';

    public const string ALICE_EMAIL = 'alice@example.com';

    public const string ALICE_PASSWORD = 'Secret1234!';

    public const string BOB_ID = '01960000-0000-0000-0000-000000000002';

    public const string BOB_EMAIL = 'bob@example.com';

    public const string BOB_PASSWORD = 'Secret1234!';

    public const string VALID_REFRESH_TOKEN = 'valid-alice-refresh-token-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';

    public const string EXPIRED_REFRESH_TOKEN = 'expired-alice-refresh-token-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';

    public const string REVOKED_REFRESH_TOKEN = 'revoked-bob-refresh-token-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';

    public function run(): void
    {
        $now = now();

        DB::table('auth.users')->insert([
            ['id' => self::ALICE_ID, 'email' => self::ALICE_EMAIL, 'password' => password_hash(self::ALICE_PASSWORD, PASSWORD_BCRYPT), 'name' => 'Alice', 'created_at' => $now, 'updated_at' => $now],
            ['id' => self::BOB_ID, 'email' => self::BOB_EMAIL, 'password' => password_hash(self::BOB_PASSWORD, PASSWORD_BCRYPT), 'name' => 'Bob', 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('auth.refresh_tokens')->insert([
            [
                'id' => '01960001-0000-0000-0000-000000000001',
                'user_id' => self::ALICE_ID,
                'token_hash' => hash('sha256', self::VALID_REFRESH_TOKEN),
                'expires_at' => $now->copy()->addDays(30),
                'revoked_at' => null,
                'created_at' => $now,
            ],
            [
                'id' => '01960001-0000-0000-0000-000000000002',
                'user_id' => self::ALICE_ID,
                'token_hash' => hash('sha256', self::EXPIRED_REFRESH_TOKEN),
                'expires_at' => $now->copy()->subDay(),
                'revoked_at' => null,
                'created_at' => $now,
            ],
            [
                'id' => '01960001-0000-0000-0000-000000000003',
                'user_id' => self::BOB_ID,
                'token_hash' => hash('sha256', self::REVOKED_REFRESH_TOKEN),
                'expires_at' => $now->copy()->addDays(30),
                'revoked_at' => $now,
                'created_at' => $now,
            ],
        ]);
    }
}
