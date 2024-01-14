<?php

namespace App\Service;

use App\Entity\Message\FailureReport;
use App\Entity\Message\Review;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use Psr\Log\LoggerInterface;

class MessageService
{
    private const string DUPLICATE = 'duplicate';
    private const string ERROR = 'error';
    public const array REVIEW_STATUS = [
        'new' => 'new',
        'scheduled' => 'scheduled',
    ];
    public const string REVIEW_TYPE = 'przegląd';
    public const array FAILURE_PRIORITY_STATUS = [
        'new' => 'new',
        'deadline' => 'deadline',
    ];
    public const array FAILURE_PRIORITY_TYPE = [
        'critical' => 'bardzo pilne',
        'high' => 'pilne',
        'normal' => '',
        ];

    public function __construct(
        private readonly EntityFactory $entityFactory,
        private EntityManagerInterface $entityManager,
        private PhoneNumberUtil        $phoneNumberUtil,
        private LoggerInterface $logger
    ) {
    }

    public function createMessage($rawMessage)
    {
        // We can't proceed with creation without description.
        if (empty($rawMessage['description'])) {
            return [self::ERROR => 'Error because of empty description.'];
        }

        // Get message entity of a needed type.
        $messageEntity = $this->getMessageTypeByDescription($rawMessage['description']);

        // Check if it is a duplicate.
        if ($this->isDuplicate($messageEntity, $rawMessage['description'])) {
            $this->logger->warning('Found duplicated message number {number} by description.', [
                'number' => $rawMessage['number'],
                'description' => $rawMessage['description'],
            ]);
            return [self::DUPLICATE => 'Already exists in database.'];
        }

        return $this->processMessage($messageEntity, $rawMessage);
    }

    /**
     * @throws NumberParseException
     */
    private function processMessage($message, $content): object | array
    {
        // The description is always available on this point.
        $message->setDescription($content['description']);

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
            if ($phone instanceof NumberParseException) {
                return [self::ERROR => 'Error while parsing phone number.'];
            }

            $message->setClientPhone($phone);
        }

        return $message;
    }

    private function processReviewMessage(Review $message, array $content)
    {
        // Set message date.
        $date = $this->massageDate($content['dueDate']);
        if ($date instanceof \DateTime) {
            $message->setReviewDate($date);
            $message->setWeekOfYear($date->format('W'));
            $message->setStatus(self::REVIEW_STATUS['scheduled']);
        } else {
            $message->setStatus(self::REVIEW_STATUS['new']);
        }
    }

    private function processFailureReportMessage(FailureReport $message, array $content)
    {
        // Set message date.
        $date = $this->massageDate($content['dueDate']);
        if ($date instanceof \DateTime) {
            $message->setDateOfServiceVisit($date);
            // If we have a date – status deadline, in another case – new.
            $message->setStatus(self::FAILURE_PRIORITY_STATUS['deadline']);
        } else {
            $message->setStatus(self::FAILURE_PRIORITY_STATUS['new']);
        }

        // Set priority for a message by description field.
        $this->setPriorityByDescription($message, $content['description']);

    }

    private function getMessageTypeByDescription($description): Review | FailureReport
    {
        return str_contains(mb_strtolower($description), self::REVIEW_TYPE)
            ? $this->entityFactory->createReview() : $this->entityFactory->createFailureReport();
    }

    private function setPriorityByDescription(FailureReport $message, $description): void
    {
        foreach (self::FAILURE_PRIORITY_TYPE as $priority => $needle) {
            if (str_contains(mb_strtolower($description), mb_strtolower($needle))) {
                $message->setPriority($priority);
                return;
            }
        }
    }

    private function isDuplicate($entity, $description): bool
    {
        return (bool) $this->entityManager->getRepository($entity::class)->findBy(['description' => $description]);
    }

    /**
     */
    private function massagePhoneNumber($phone): PhoneNumber | \Exception|NumberParseException
    {
        $parsedPhone = null;
        try {
            $parsedPhone = $this->phoneNumberUtil->parse($phone, 'PL');
        } catch (NumberParseException $e) {
            $this->logger->error('Error while parsing phone number: {phone}.', [
                'phone' => $phone,
                'exception' => $e,
            ]);
            return $e;
        }
        return $parsedPhone;
    }

    /**
     * @throws \Exception
     */
    private function massageDate(mixed $dueDate): DateTime|false
    {
        $time = false;
        $format = 'Y-m-d';
        if (!empty($dueDate)) {
            $time = (new DateTime($dueDate))->format($format);
        }

        return $time ? DateTime::createFromFormat($format, $time) : false;
    }
}
