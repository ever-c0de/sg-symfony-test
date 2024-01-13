<?php

namespace App\Service;

use App\Entity\Message\FailureReport;
use App\Entity\Message\Review;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\NumberParseException;
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

        // Check if it is a duplicate.
        if ($this->isDuplicate($messageEntity, $rawMessage['description'])) {
            $this->logger->warning('Found duplicated message number {number} by description.', [
                'number' => $rawMessage['number'],
                'description' => $rawMessage['description'],
            ]);
            return self::DUPLICATE;
        }

        $message = $this->processMessage($messageEntity, $rawMessage);

        return $messageEntity;
    }

    /**
     * @throws NumberParseException
     */
    private function processMessage($message, $content): object
    {
        // Process Review type message.
        if ($message instanceof Review) {
            $this->processReviewMessage($message, $content);
        }

        if ($message instanceof FailureReport) {
            $this->processFailureReportMessage($message, $content);
        }

        // Set phone number if available.
        if (!empty($phone = $content['phone'])) {
            $phone = $this->massagePhoneNumber($phone);
            $message->setClientPhone($phone);
        }

        return $message;
    }

    private function processReviewMessage(Review $message, array $content)
    {
        // Set message date.
        if (!empty($date = $content['dueDate'])){
            $date = $this->massageDate($date);
            $message->setReviewDate($date);
            $message->setWeekOfYear(\DateTime::createFromFormat('W', $date));
            $message->setStatus('scheduled');
        } else {
            $message->setStatus('new');
        }
    }

    private function processFailureReportMessage(FailureReport $message, array $content)
    {
        // Set message date.
        if (!empty($date = $content['dueDate'])){
            $date = $this->massageDate($date);
            $message->setDateOfServiceVisit($date);
        }

        // Set priority for message by description field.
        $this->setPriorityByDescription($message, $content['description']);

    }

    private function getMessageTypeByDescription($description): Review | FailureReport
    {
        return str_contains($description, self::REVIEW_TYPE)
            ? $this->entityFactory->createReview() : $this->entityFactory->createFailureReport();
    }

    private function setPriorityByDescription(FailureReport $message, $description)
    {
        $priorityType = self::FAILURE_PRIORITY_TYPE['normal'];

        foreach (self::FAILURE_PRIORITY_TYPE as $priority => $needle) {
            if (str_contains($description, $needle)) {
                $priorityType = $priority;
            }
        }

        return $message->setPriority($priorityType);
    }

    private function isDuplicate($entity, $description): bool
    {
        return (bool) $this->entityManager->getRepository($entity::class)->findBy(['description' => $description]);
    }

    /**
     * @throws NumberParseException
     */
    private function massagePhoneNumber($phone): string
    {
        try {
            $parsedPhone = $this->phoneNumberUtil->parse($phone, 'PL');
        } catch (NumberParseException $e) {
            $this->logger->error('Error while parsing phone number: {phone}.', [
                'phone' => $phone,
                'exception' => $e,
            ]);
        }
        return $parsedPhone->__toString ?? '';
    }

    private function massageDate(mixed $dueDate): \DateTime|false
    {
        return \DateTime::createFromFormat('Y-m-d', $dueDate);
    }
}
