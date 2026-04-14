<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\Request\EditArticleRequest;
use App\Entity\Article;
use App\Entity\Image;
use App\Entity\ImageBatch;
use App\Entity\User;
use App\Enum\Category;
use App\Enum\Condition;
use App\Message\GroupImagesMessage;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;

class ArticleService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImageService $imageService,
        private readonly MessageBusInterface $messageBus,
        private readonly string $publicDirectory,
    ) {
    }

    /**
     * Upload images, create an ImageBatch, and dispatch for async AI processing.
     *
     * @param UploadedFile[] $images
     *
     * @return ImageBatch the batch entity (with id and jobId set)
     */
    public function uploadImages(array $images, User $user): ImageBatch
    {
        $imagePaths = array_map($this->imageService->uploadImage(...), $images);

        foreach ($imagePaths as $i => $imagePath) {
            $quality = (int) ceil(100 / ($this->imageService->getFileSize($this->publicDirectory.$imagePath) / 1024 / 1024));

            $dirname = (string) pathinfo($imagePath, PATHINFO_DIRNAME);
            $newPath = $this->imageService->convertToJpeg(
                $this->publicDirectory.$imagePath,
                $this->publicDirectory.$dirname,
                min($quality, 100),
            );
            $imagePaths[$i] = str_replace($this->publicDirectory, '', $newPath);

            $this->imageService->resizeForVision($newPath);
        }

        $batch = new ImageBatch();
        $batch->setJobId(uniqid('group_', true));
        $batch->setUser($user);
        $batch->setImagePaths($imagePaths);
        $batch->setStatus('processing');

        $this->em->persist($batch);
        $this->em->flush();

        $this->messageBus->dispatch(new GroupImagesMessage($batch->getId()));

        return $batch;
    }

    /**
     * Create Article entities from AI grouping results.
     *
     * @param array<array{
     *   group_name: string,
     *   description: string,
     *   images: string[],
     *   category?: string,
     *   condition?: string,
     *   extracted_fields?: array<string, mixed>,
     *   vehicleData?: array<string, mixed>
     * }> $groupedData
     *
     * @return Article[]
     */
    public function createArticlesFromGroups(array $groupedData, User $user): array
    {
        if (empty($groupedData)) {
            return [];
        }

        $imagesDirectory = $this->publicDirectory.'/'.ImageService::IMAGES_DIRECTORY.'/';

        $newArticles = [];
        foreach ($groupedData as $articleData) {
            $article = new Article();
            $this->em->persist($article);
            $article->setTitle($articleData['group_name']);
            $article->setDescription($articleData['description']);

            // Set category from AI detection
            if (!empty($articleData['category'])) {
                $category = Category::tryFrom($articleData['category']);
                if ($category) {
                    $article->setCategory($category);
                }
            }

            // Set condition from AI detection
            if (!empty($articleData['condition'])) {
                $condition = Condition::tryFrom($articleData['condition']);
                if ($condition) {
                    $article->setCondition($condition);
                }
            }

            // Merge extracted_fields with vehicleData (vehicleData takes priority)
            $meta = [];
            if (!empty($articleData['extracted_fields'])) {
                $meta = $articleData['extracted_fields'];
            }
            if (!empty($articleData['vehicleData'])) {
                $meta = array_merge($meta, $articleData['vehicleData']);
            }
            if (!empty($meta)) {
                $article->setMeta($meta);
            }

            $this->em->flush();

            $articleId = (string) $article->getId();
            $articleData['images'] = array_map(
                fn (string $filePath) => $this->imageService->moveImage(
                    $this->publicDirectory.'/'.ltrim($filePath, '/'),
                    $imagesDirectory.$articleId.'/'.basename($filePath),
                ),
                $articleData['images'],
            );

            $images = [];
            foreach (array_values($articleData['images']) as $index => $imagePath) {
                $images[] = (new Image())
                    ->setLink(str_replace($this->publicDirectory, '', $imagePath))
                    ->setArticle($article)
                    ->setPosition($index);
            }
            $article->setImages(new ArrayCollection($images));
            $article->setUser($user);

            $newArticles[] = $article;
            $this->em->persist($article);
        }
        $this->em->flush();

        return $newArticles;
    }

    public function updateArticle(Article $article, EditArticleRequest $request): void
    {
        if (null !== $request->title) {
            $article->setTitle($request->title);
        }
        if (null !== $request->description) {
            $article->setDescription($request->description);
        }
        $article->setPrice($request->price);

        $imagesDirectory = $this->publicDirectory.'/'.ImageService::IMAGES_DIRECTORY.'/';

        foreach ($request->images as $tmpImage) {
            $imagePath = $this->imageService->uploadImage($tmpImage, $imagesDirectory.$article->getId());
            $img = new Image()->setLink($imagePath)->setArticle($article);
            $this->em->persist($img);
        }

        $this->em->persist($article);
        $this->em->flush();
    }

    public function deleteArticle(Article $article): void
    {
        $this->em->remove($article);
        $this->em->flush();
    }

    public function deleteImage(Image $image): void
    {
        $this->em->remove($image);
        $this->imageService->deleteImageFile($this->publicDirectory.'/'.$image->getLink());
        $this->em->flush();
    }
}
