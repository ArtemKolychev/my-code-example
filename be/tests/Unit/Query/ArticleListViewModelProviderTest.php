<?php

declare(strict_types=1);

namespace App\Tests\Unit\Query;

use App\Application\DTO\Response\ArticleListViewModel;
use App\Application\Query\ArticleListViewModelProvider;
use App\Application\Query\ArticleSubmissionQueryServiceInterface;
use App\Domain\Entity\Article;
use App\Domain\Entity\User;
use App\Domain\Enum\Platform;
use App\Tests\Shared\Mother\ArticleMother;
use App\Tests\Shared\Mother\ArticleSubmissionMother;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ArticleListViewModelProviderTest extends TestCase
{
    private ArticleSubmissionQueryServiceInterface&MockObject $submissionRepository;
    private ArticleListViewModelProvider $provider;

    public function testReturnsEmptyViewModelWhenUserHasNoArticles(): void
    {
        $user = $this->makeUser([]);

        $this->submissionRepository->expects($this->once())
            ->method('findAllByArticles')
            ->with([])
            ->willReturn([]);

        $vm = $this->provider->getForUser($user);

        $this->assertInstanceOf(ArticleListViewModel::class, $vm);
        $this->assertSame([], $vm->articles);
        $this->assertSame([], $vm->submissions);
    }

    public function testGroupsSubmissionsByArticleIdAndPlatform(): void
    {
        $article1 = ArticleMother::withId(1);
        $article2 = ArticleMother::withId(2);

        $sub1 = ArticleSubmissionMother::completed(Platform::Seznam);
        $sub2 = ArticleSubmissionMother::pending(Platform::Bazos);

        $user = $this->makeUser([$article1, $article2]);

        $this->submissionRepository->expects($this->once())
            ->method('findAllByArticles')
            ->willReturn([
                1 => [$sub1],
                2 => [$sub2],
            ]);

        $vm = $this->provider->getForUser($user);

        $this->assertSame($sub1, $vm->submissions[1][Platform::Seznam->value]);
        $this->assertSame($sub2, $vm->submissions[2][Platform::Bazos->value]);
    }

    public function testArticleWithNoSubmissionsHasEmptySubmissionsEntry(): void
    {
        $article = ArticleMother::withId(5);
        $user = $this->makeUser([$article]);

        $this->submissionRepository->method('findAllByArticles')->willReturn([]);

        $vm = $this->provider->getForUser($user);

        $this->assertCount(1, $vm->articles);
        $this->assertArrayNotHasKey(5, $vm->submissions);
    }

    public function testIssuesSingleQueryForMultipleArticles(): void
    {
        $articles = [ArticleMother::withId(10), ArticleMother::withId(11), ArticleMother::withId(12)];
        $user = $this->makeUser($articles);

        // Must be called exactly once — no N+1
        $this->submissionRepository->expects($this->once())
            ->method('findAllByArticles')
            ->willReturn([]);

        $this->provider->getForUser($user);
    }

    protected function setUp(): void
    {
        $this->submissionRepository = $this->createMock(ArticleSubmissionQueryServiceInterface::class);
        $this->provider = new ArticleListViewModelProvider($this->submissionRepository);
    }

    /** @param Article[] $articles */
    private function makeUser(array $articles): User
    {
        $user = new User();
        $user->articles = new ArrayCollection($articles);

        return $user;
    }
}
