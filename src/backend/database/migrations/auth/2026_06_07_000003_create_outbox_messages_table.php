<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS auth.outbox_messages (
                id BIGSERIAL PRIMARY KEY,
                event_type VARCHAR(255) NOT NULL,
                payload JSONB NOT NULL,
                published_at TIMESTAMPTZ NULL,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ');

        DB::statement('
            CREATE INDEX IF NOT EXISTS outbox_unpublished_idx
            ON auth.outbox_messages (created_at ASC)
            WHERE published_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS auth.outbox_messages CASCADE');
    }
};
