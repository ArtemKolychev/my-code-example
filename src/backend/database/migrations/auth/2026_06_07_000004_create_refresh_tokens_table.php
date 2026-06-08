<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS auth.refresh_tokens (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
                token_hash VARCHAR(255) NOT NULL UNIQUE,
                expires_at TIMESTAMPTZ NOT NULL,
                revoked_at TIMESTAMPTZ NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ');

        DB::statement('CREATE INDEX IF NOT EXISTS refresh_tokens_user_idx ON auth.refresh_tokens (user_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS refresh_tokens_hash_idx ON auth.refresh_tokens (token_hash)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS auth.refresh_tokens CASCADE');
    }
};
