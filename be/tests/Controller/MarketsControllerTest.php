<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\ImageBatch;
use App\Entity\User;
use App\Kernel;
use App\Service\ArticleService;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MarketsControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private User $user;

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        foreach ($em->getRepository(User::class)->findAll() as $existing) {
            $em->remove($existing);
        }
        $em->flush();

        $this->user = (new User())
            ->setEmail('market@example.com')
            ->setPassword('irrelevant');

        $em->persist($this->user);
        $em->flush();
    }

    public function testAddImagesSubmitsUploadedFilesAndRedirectsToProcessing(): void
    {
        $this->client->loginUser($this->user);

        $batch = new ImageBatch();
        $batch->id = 321;

        /** @var ArticleService&MockObject $articleService */
        $articleService = $this->createMock(ArticleService::class);
        $articleService
            ->expects($this->once())
            ->method('uploadImages')
            ->with(
                $this->callback(static function (array $images): bool {
                    return 1 === count($images) && $images[0] instanceof UploadedFile;
                }),
                $this->isInstanceOf(User::class),
            )
            ->willReturn($batch);

        // Disable kernel reboot so the mock persists across both GET and POST requests.
        // By default KernelBrowser reboots between requests, which would wipe the mock.
        $this->client->disableReboot();
        static::getContainer()->set(ArticleService::class, $articleService);

        $crawler = $this->client->request('GET', '/market/article/add_images');
        self::assertResponseIsSuccessful();

        $token = $crawler->filter('input[name="add_article_images[_token]"]')->attr('value');
        self::assertNotNull($token);

        $tmpFile = tempnam(sys_get_temp_dir(), 'market-image-');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, 'fake-image-content');

        $uploadedFile = new UploadedFile(
            $tmpFile,
            'test.jpg',
            'image/jpeg',
            null,
            true,
        );

        try {
            $this->client->request(
                'POST',
                '/market/article/add_images',
                [
                    'add_article_images' => [
                        '_token' => $token,
                    ],
                ],
                [
                    'add_article_images' => [
                        'images' => [$uploadedFile],
                    ],
                ],
            );
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }

        self::assertResponseRedirects('/market/processing/321');
    }
}
