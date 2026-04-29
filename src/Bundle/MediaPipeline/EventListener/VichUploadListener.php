<?php

namespace App\Bundle\MediaPipeline\EventListener;

use App\Bundle\MediaPipeline\Service\ImageProcessorService;
use App\Entity\ListingImage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Event\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * VichUploadListener
 *
 * Fires after VichUploaderBundle saves a file.
 * Triggers the ImageProcessorService to generate WebP srcsets
 * and updates the ListingImage entity with the results.
 */
#[AsEventListener(event: Events::POST_UPLOAD, method: 'onPostUpload')]
class VichUploadListener
{
    public function __construct(
        private readonly ImageProcessorService  $processor,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface        $logger,
        private readonly string                 $projectDir,
    ) {}

    public function onPostUpload(Event $event): void
    {
        $entity = $event->getObject();

        // Only process ListingImage entities (listing_images mapping)
        if (!$entity instanceof ListingImage) {
            return;
        }

        $imagePath = $entity->getImagePath();
        if (!$imagePath) return;

        $absolutePath = $this->projectDir . '/public/uploads/listings/' . $imagePath;

        try {
            $result = $this->processor->process($absolutePath);

            if ($result) {
                // Store srcset as JSON in the entity (column added by migration)
                if (method_exists($entity, 'setSrcset')) {
                    $entity->setSrcset($result['srcset']);
                    $this->em->flush();
                }
            }
        } catch (\Throwable $e) {
            // Non-blocking — pipeline failures must never break uploads
            $this->logger->warning('MediaPipeline: image processing failed: ' . $e->getMessage(), [
                'path' => $absolutePath,
            ]);
        }
    }
}
