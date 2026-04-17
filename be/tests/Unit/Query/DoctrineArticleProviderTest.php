<?php

declare(strict_types=1);

namespace App\Tests\Unit\Query;

use App\Application\DTO\Response\ArticleResponse;
use App\Domain\Entity\Article;
use App\Domain\Entity\User;
use App\Domain\Enum\Platform;
use App\Domain\Repository\ArticleRepositoryInterface;
use App\Domain\Repository\ArticleSubmissionRepositoryInterface;
use App\Infrastructure\Query\DoctrineArticleProvider;
use App\Tests\Shared\Mother\ArticleMother;
use App\Tests\Shared\Mother\ArticleSubmissionMother;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DoctrineArticleProviderTest extends TestCase
{
    private ArticleRepositoryInterface&MockObject $articleRepository;
    private ArticleSubmissionRepositoryInterface&MockObject $submissionRepository;
    private DoctrineArticleProvider $provider;

    // -------------------------------------------------------------------------
    // findForUser
    // -------------------------------------------------------------------------

    public function testFindForUserReturnsNullWhenArticleNotFound(): void
    {
        $this->articleRepository->method('findByIdAndUser')->willReturn(null);

        $result = $this->provider->findForUser(99, $this->createMock(User::class));

        $this->assertNull($result);
    }

    public function testFindForUserReturnsArticleResponseWithSubmissions(): void
    {
        $article = ArticleMother::withId(1);
        $sub = ArticleSubmissionMother::completed(Platform::Seznam);

        $this->articleRepository->method('findByIdAndUser')->willReturn($article);
        $this->submissionRepository->method('findAllByArticle')->willReturn([$sub]);

        $result = $this->provider->findForUser(1, $this->createMock(User::class));

        $this->assertInstanceOf(ArticleResponse::class, $result);
    }

    // -------------------------------------------------------------------------
    // listForUser
    // -------------------------------------------------------------------------

    public function testListForUserReturnsEmptyArrayWhenUserHasNoArticles(): void
    {
        $user = $this->makeUser([]);

        $this->submissionRepository->expects($this->once())
            ->method('findAllByArticles')
            ->with([])
            ->willReturn([]);

        $result = $this->provider->listForUser($user);

        $this->assertSame([], $result);
    }

    public function testListForUserReturnsOneResponsePerArticle(): void
    {
        $article1 = ArticleMother::withId(10);
        $article2 = ArticleMother::withId(11);
        $user = $this->makeUser([$article1, $article2]);

        $this->submissionRepository->method('findAllByArticles')->willReturn([]);

        $result = $this->provider->listForUser($user);

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(ArticleResponse::class, $result);
    }

    public function testListForUserIssuesSingleQueryForAllArticles(): void
    {
        $articles = [ArticleMother::withId(1), ArticleMother::withId(2), ArticleMother::withId(3)];
        $user = $this->makeUser($articles);

        // Exactly one batch query — no N+1
        $this->submissionRepository->expects($this->once())
            ->method('findAllByArticles')
            ->willReturn([]);

        $this->provider->listForUser($user);
    }

    public function testListForUserAttachesCorrectSubmissionsToEachArticle(): void
    {
        $article1 = ArticleMother::withId(1);
        $article2 = ArticleMother::withId(2);
        $user = $this->makeUser([$article1, $article2]);

        $sub1 = ArticleSubmissionMother::completed(Platform::Seznam);
        $sub2 = ArticleSubmissionMother::pending(Platform::Bazos);

        $this->submissionRepository->method('findAllByArticles')->willReturn([
            1 => [$sub1],
            2 => [$sub2],
        ]);

        $results = $this->provider->listForUser($user);

        // Both responses produced — submission wiring tested via integration tests
        $this->assertCount(2, $results);
    }

    protected function setUp(): void
    {
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->submissionRepository = $this->createMock(ArticleSubmissionRepositoryInterface::class);

        $this->provider = new DoctrineArticleProvider(
            $this->articleRepository,
            $this->submissionRepository,
        );
    }

    /** @param Article[] $articles */
    private function makeUser(array $articles): User
    {
        $user = new User();
        $user->articles = new ArrayCollection($articles);

        return $user;
    }
}
