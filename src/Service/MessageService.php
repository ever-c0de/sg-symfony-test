<?php

namespace App\Service;

use App\Entity\Message\FailureReport;
use App\Entity\Message\Review;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Filesystem\Filesystem;

class MessageService
{
    public const string REVIEW_TYPE = 'przegląd';

    public const array FAILURE_PRIORITY_TYPE = [
        'critical' => 'bardzo pilne',
        'high' => 'pilne',
        'normal' => '',
        ];

    public function __construct(
        private readonly EntityFactory $entityFactory,
        private Filesystem             $filesystem,
        private PhoneNumberUtil        $phoneNumberUtil
    ) {
    }

    public function createMessage($rawMessage)
    {
        $messageEntity = $this->getMessageType($rawMessage['description']);

    }

    private function processMessage()
    {

    }

    private function getMessageType($description): Review | FailureReport
    {
        return str_contains($description, self::REVIEW_TYPE) ?
            $this->entityFactory->createReview() : $this->entityFactory->createFailureReport();
    }
}
