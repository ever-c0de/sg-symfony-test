<?php

use App\Entity\Message\FailureReport;
use App\Entity\Message\Review;
use App\Service\EntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EntityFactoryTest extends KernelTestCase
{
    private EntityFactory $entityFactory;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();

        $container = static::getContainer();
        $this->entityFactory = $container->get(EntityFactory::class);
    }
    public function testReviewCreation(): void
    {
        $review = $this->entityFactory->createReview();

        $this->assertInstanceOf(Review::class, $review);
    }

    public function testFailureReportCreation(): void
    {
        $failureReport = $this->entityFactory->createFailureReport();

        $this->assertInstanceOf(FailureReport::class, $failureReport);
    }
}
