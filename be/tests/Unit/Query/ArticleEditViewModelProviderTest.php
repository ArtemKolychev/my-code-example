<?php

declare(strict_types=1);

namespace App\Tests\Unit\Query;

use App\Application\Query\ArticleEditViewModelProvider;
use App\Application\Query\ArticleSubmissionQueryServiceInterface;
use App\Application\Query\AvailablePlatformsProvider;
use App\Domain\Entity\User;
use App\Domain\Enum\Platform;
use App\Tests\Shared\Mother\ArticleMother;
use App\Tests\Shared\Mother\ArticleSubmissionMother;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ArticleEditViewModelProviderTest extends TestCase
{
    private ArticleSubmissionQueryServiceInterface&MockObject $submissionRepository;
    private ArticleEditViewModelProvider $provider;

    public function testReturnsEmptySubmissionsMapForNoArticles(): void
    {
        $this->submissionRepository->expects($this->once())
            ->method('findAllByArticles')
            ->with([])
            ->willReturn([]);

        $vm = $this->provider->build([], $this->createMock(User::class));

        $this->assertSame([], $vm->submissions);
    }

    public function testGroupsSubmissionsByArticleIdAndPlatformValue(): void
    {
        $article = ArticleMother::withId(7);

        $subSeznam = ArticleSubmissionMother::completed(Platform::Seznam);
        $subBazos = ArticleSubmissionMother::pending(Platform::Bazos);

        $this->submissionRepository->expects($this->once())
            ->method('findAllByArticles')
            ->willReturn([7 => [$subSeznam, $subBazos]]);

        $vm = $this->provider->build([$article], $this->createMock(User::class));

        $this->assertSame($subSeznam, $vm->submissions[7][Platform::Seznam->value]);
        $this->assertSame($subBazos, $vm->submissions[7][Platform::Bazos->value]);
    }

    public function testArticleWithNoSubmissionsGetsEmptyMap(): void
    {
        $article = ArticleMother::withId(3);

        $this->submissionRepository->method('findAllByArticles')->willReturn([]);

        $vm = $this->provider->build([$article], $this->createMock(User::class));

        $this->assertSame([], $vm->submissions[3]);
    }

    public function testIssuesSingleQueryForAllArticles(): void
    {
        $articles = [ArticleMother::withId(1), ArticleMother::withId(2), ArticleMother::withId(3)];

        // Must be called exactly once — no N+1
        $this->submissionRepository->expects($this->once())
            ->method('findAllByArticles')
            ->willReturn([]);

        $this->provider->build($articles, $this->createMock(User::class));
    }

    protected function setUp(): void
    {
        $this->submissionRepository = $this->createMock(ArticleSubmissionQueryServiceInterface::class);

        $this->provider = new ArticleEditViewModelProvider(
            $this->submissionRepository,
            new AvailablePlatformsProvider(),
        );
    }
}
