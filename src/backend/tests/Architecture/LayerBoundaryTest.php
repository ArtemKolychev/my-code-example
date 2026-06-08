<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class LayerBoundaryTest extends TestCase
{
    // ── Domain purity ────────────────────────────────────────────────────────

    #[Test]
    public function auth_domain_has_no_framework_imports(): void
    {
        $this->assertNoForbiddenImports(
            dir: 'app/Auth/Domain',
            forbidden: ['use Illuminate\\'],
            reason: 'Domain must be pure — no framework imports allowed.',
        );
    }

    #[Test]
    public function users_domain_has_no_framework_imports(): void
    {
        $this->assertNoForbiddenImports(
            dir: 'app/Users/Domain',
            forbidden: ['use Illuminate\\'],
            reason: 'Domain must be pure — no framework imports allowed.',
        );
    }

    #[Test]
    public function tenants_domain_has_no_framework_imports(): void
    {
        $this->assertNoForbiddenImports(
            dir: 'app/Tenants/Domain',
            forbidden: ['use Illuminate\\'],
            reason: 'Domain must be pure — no framework imports allowed.',
        );
    }

    // ── Application isolation ────────────────────────────────────────────────

    #[Test]
    public function auth_application_does_not_depend_on_infra_or_http(): void
    {
        $this->assertNoForbiddenImports(
            dir: 'app/Auth/Application',
            forbidden: ['use App\\Auth\\Http\\', 'use App\\Auth\\Infrastructure\\'],
            reason: 'Application layer must not depend on Http or Infrastructure.',
        );
    }

    #[Test]
    public function users_application_does_not_depend_on_infra_or_http(): void
    {
        $this->assertNoForbiddenImports(
            dir: 'app/Users/Application',
            forbidden: ['use App\\Users\\Http\\', 'use App\\Users\\Infrastructure\\'],
            reason: 'Application layer must not depend on Http or Infrastructure.',
        );
    }

    #[Test]
    public function tenants_application_does_not_depend_on_infra_or_http(): void
    {
        $this->assertNoForbiddenImports(
            dir: 'app/Tenants/Application',
            forbidden: ['use App\\Tenants\\Http\\', 'use App\\Tenants\\Infrastructure\\'],
            reason: 'Application layer must not depend on Http or Infrastructure.',
        );
    }

    // ── Cross-module isolation ────────────────────────────────────────────────

    #[Test]
    public function auth_module_does_not_depend_on_users_or_tenants(): void
    {
        $this->assertNoForbiddenImports(
            dir: 'app/Auth',
            forbidden: ['use App\\Users\\', 'use App\\Tenants\\'],
            reason: 'Auth module must not depend on Users or Tenants internals.',
        );
    }

    #[Test]
    public function users_module_does_not_depend_on_auth_or_tenants(): void
    {
        $this->assertNoForbiddenImports(
            dir: 'app/Users',
            forbidden: ['use App\\Auth\\', 'use App\\Tenants\\'],
            reason: 'Users module must not depend on Auth or Tenants internals.',
        );
    }

    #[Test]
    public function tenants_module_does_not_depend_on_auth_or_users(): void
    {
        $this->assertNoForbiddenImports(
            dir: 'app/Tenants',
            forbidden: ['use App\\Auth\\', 'use App\\Users\\'],
            reason: 'Tenants module must not depend on Auth or Users internals.',
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @param list<string> $forbidden */
    private function assertNoForbiddenImports(string $dir, array $forbidden, string $reason): void
    {
        $basePath = dirname(__DIR__, 2);
        $fullDir = $basePath.'/'.$dir;

        if (! is_dir($fullDir)) {
            $this->markTestSkipped("Directory {$dir} does not exist yet.");
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fullDir));

        foreach ($iterator as $file) {
            if (! ($file instanceof \SplFileInfo)) {
                continue;
            }

            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());
            $relativePath = str_replace($basePath.'/', '', $file->getPathname());

            foreach ($forbidden as $import) {
                $this->assertStringNotContainsString(
                    $import,
                    $content,
                    "{$relativePath} violates boundary: {$reason}",
                );
            }
        }
    }
}
