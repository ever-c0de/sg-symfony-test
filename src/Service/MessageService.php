<?php

namespace App\Service;

use App\Entity\Message\FailureReport;
use App\Entity\Message\Review;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\HttpKernel\Log\Logger;

class MessageService
{
    private const string DUPLICATE = 'duplicate';

    private const string ERROR = 'error';
    public const string REVIEW_TYPE = 'przegląd';

    public const array FAILURE_PRIORITY_TYPE = [
        'critical' => 'bardzo pilne',
        'high' => 'pilne',
        'normal' => '',
        ];

    public function __construct(
        private readonly EntityFactory $entityFactory,
        private EntityManagerInterface $entityManager,
        private PhoneNumberUtil        $phoneNumberUtil,
        private Logger $logger
    ) {
    }

    public function createMessage($rawMessage)
    {
        // Get message entity of needed type.
        $messageEntity = $this->getMessageTypeByDescription($rawMessage['description']);

        // Check if it is not a duplicate.
        if ($this->isDuplicate($messageEntity, $rawMessage['description'])) {
            $this->logger->warning('Found duplicated message number {number} by description.', [
                'number' => $rawMessage['number'],
                'description' => $rawMessage['description'],
            ]);
            return self::DUPLICATE;
        }

        return $messageEntity;
    }

    private function processMessage()
    {

    }

    private function getMessageTypeByDescription($description): Review | FailureReport
    {
        return str_contains($description, self::REVIEW_TYPE)
            ? $this->entityFactory->createReview() : $this->entityFactory->createFailureReport();
    }

    private function isDuplicate($entity, $description): bool
    {
        return (bool) $this->entityManager->getRepository($entity::class)->findBy(['description' => $description]);
    }

    private function setFields()
    {

    }
}
