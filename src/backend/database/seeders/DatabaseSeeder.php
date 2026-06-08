<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Auth\Tests\Fixtures\AuthTestSeeder;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AuthTestSeeder::class,
        ]);
    }
}
