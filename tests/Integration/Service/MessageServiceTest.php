<?php

use App\Entity\Message\FailureReport;
use App\Entity\Message\Review;
use App\Service\MessageService;
use Doctrine\ORM\EntityManager;
use libphonenumber\PhoneNumberUtil;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MessageServiceTest extends KernelTestCase
{
    private MessageService $messageService;
    private PhoneNumberUtil $phoneNumberUtil;
    private EntityManager $entityManager;

    private const array REVIEW_ENTITY = [
        "number" => 17,
        "description" => "Mam na panów złą wiadomość. Zapraszam na ponowny przegląd maty zabezpieczającej w meblu kasowym. Ostatnio był a sprzęt niestety nie działa, pilne!",
        "dueDate" => "2020-03-02 00:00:00",
        "phone" => "+48505167301",
    ];

    private const array FAILURE_REPORT = [
        "number" => 15,
        "description" => "Krajalnice mięso. Nie działa tarczka głównej maszyny bardzo pilne.",
        "dueDate" => "",
        "phone" => "888241636",
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $kernel = self::bootKernel();
        $container = static::getContainer();

        $this->messageService = $container->get(MessageService::class);
        $this->phoneNumberUtil = $container->get(PhoneNumberUtil::class);

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    public function testSuccessfullCreationReviewEntity()
    {
//        $reviewEntity = $this->messageService->createMessage(self::REVIEW_ENTITY);

        $reviewTestEntity = new Review();
        $reviewTestEntity->setDescription(self::REVIEW_ENTITY['description']);
        $time = (new DateTime(self::REVIEW_ENTITY['dueDate']))->format('Y-m-d');
        $reviewTestEntity->setReviewDate(DateTime::createFromFormat('Y-m-d', $time));
        $reviewTestEntity->setWeekOfYear((new DateTime($time))->format('W'));
        $reviewTestEntity->setStatus('scheduled');
        $reviewTestEntity->setClientPhone($this->phoneNumberUtil->parse(self::REVIEW_ENTITY['phone']));

//        $this->assertEquals($reviewTestEntity, $reviewEntity);
    }

}
