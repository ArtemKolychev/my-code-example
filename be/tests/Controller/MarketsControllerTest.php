<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Application\Service\ImageUploadService;
use App\Domain\Entity\User;
use App\Kernel;
use App\Tests\Shared\Mother\ImageBatchMother;
use App\Tests\Shared\Mother\UserMother;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

final class MarketsControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private User $user;

    #[Override]
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testAddImagesSubmitsUploadedFilesAndRedirectsToProcessing(): void
    {
        $this->client->loginUser($this->user);

        $batch = ImageBatchMother::withId(321);

        /** @var ImageUploadService&MockObject $imageUploadService */
        $imageUploadService = $this->createMock(ImageUploadService::class);
        $imageUploadService
            ->expects($this->once())
            ->method('uploadImages')
            ->with(
                $this->callback(static fn (array $images): bool => 1 === count($images) && $images[0] instanceof UploadedFile),
                $this->isInstanceOf(User::class),
            )
            ->willReturn($batch);

        // Disable kernel reboot so the mock persists across both GET and POST requests.
        // By default KernelBrowser reboots between requests, which would wipe the mock.
        $this->client->disableReboot();
        static::getContainer()->set(ImageUploadService::class, $imageUploadService);

        $crawler = $this->client->request(Request::METHOD_GET, '/market/article/add_images');
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
                Request::METHOD_POST,
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

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.entity_manager');

        foreach ($em->getRepository(User::class)->findAll() as $existing) {
            $em->remove($existing);
        }
        $em->flush();

        $this->user = UserMother::withEmail('market@example.com')
            ->setPassword('irrelevant');

        $em->persist($this->user);
        $em->flush();
    }
}
