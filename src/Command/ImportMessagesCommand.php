<?php

namespace App\Command;

use App\Entity\Message\FailureReport;
use App\Entity\Message\Review;
use App\Service\MessageService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;

#[AsCommand(
    name: 'app:import-messages',
    description: 'Imports messages as entities from JSON type source file. Outputs the final results',
    aliases: ['app:im, aim']
)]
class ImportMessagesCommand extends Command
{
    private const array MESSAGE_TYPES = [
      Review::class,
      FailureReport::class,
    ];

    /**
     * {@inheritDoc}
     */
    public function __construct(private Filesystem $fileSystem, private MessageService $messageService, private EntityManagerInterface $entityManager, private SerializerInterface $serializer, private KernelInterface $kernel, private LoggerInterface $logger)
    {
        parent::__construct();
    }

    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this->addArgument('filePath', InputArgument::REQUIRED, 'Path of the source file');
    }

    /**
     *
     * Process messages from given source file and create entities.
     *
     * @throws \JsonException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('filePath');
        $io->note(sprintf('Provided filepath to the source: %s', $filePath));
        $this->logger->warning('Command executed with file path "{filePath}".', [
            'command' => $this->getName(),
            'filePath' => $filePath,
        ]);

        // Check if the source file exists.
        if (!$this->checkFileExist($filePath)) {
            $io->error(sprintf('Provided filepath to the source is invalid: %s.', $filePath));
            $this->logger->warning('Provided filepath to the source is invalid.', [
                'command' => $this->getName(),
                'filePath' => $filePath,
            ]);
            return Command::INVALID;
        }

        // Check if the source file readable.
        if (!($encodedMessages = file_get_contents($filePath)) && json_validate($encodedMessages)) {
            $io->error(sprintf('Cannot read contents of source file or JSON is incorrect: %s', $filePath));
            $this->logger->warning('File is empty or invalidate JSON on import.', [
                'command' => $this->getName(),
                'filePath' => $filePath,
            ]);
        }

        // Initiate variables.
        $resultFiles = [];
        $reviews = [];
        $failureReports = [];
        $duplicates = [];
        $errors = [];

        // Decode the messages from JSON format.
        try {
            $decodedMessages = json_decode($encodedMessages, true, 512, JSON_THROW_ON_ERROR);
            $this->logger->info('Started import of messages from source.', [
                'command' => $this->getName(),
                'filePath' => $filePath,
                'file_format' => 'json',
            ]);

            foreach ($decodedMessages as $message) {
                // Try to migrate a message to entities for each message.
                $messageEntity = $this->messageService->createMessage($message);
                // Check for error/duplicate. Variable is array only if we have some error.
                if (is_array($messageEntity)) {
                    $this->checkErrorType($message, $messageEntity, $duplicates, $errors);
                    continue;
                }

                // Save entities (not yet in a database).
                $this->entityManager->persist($messageEntity);
                // Query added entities.
                $this->entityManager->flush();
                // Serialize all created message entities in JSON format.
                if ($messageEntity instanceof Review) {
                    $reviews[] = $this->serializeEntity($messageEntity);
                } elseif ($messageEntity instanceof FailureReport) {
                    $failureReports[] = $this->serializeEntity($messageEntity);
                }
            }
        } catch (JsonException $e) {
            $io->error(sprintf('JSON is broken in file: %s', $filePath));
            $this->logger->warning('JSON file decoding failed.', [
                'command' => $this->getName(),
                'filePath' => $filePath,
                'exception' => $e
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error in is not found in match function.', [
                'command' => $this->getName(),
                'filePath' => $filePath,
                'exception' => $e
            ]);
        }

        // Create a directory for results.
        $resultsDir = $this->kernel->getProjectDir() . '/results';
        $this->fileSystem->mkdir($resultsDir);

        // Create a report for FailureReports message type.
        $this->createRaportFile($reviews, $resultFiles, $resultsDir, Review::class);

        // Create a report for FailureReports message type.
        $this->createRaportFile($failureReports, $resultFiles, $resultsDir, FailureReport::class);

        // Create a report for duplicates.
        $this->createRaportFile($duplicates, $resultFiles, $resultsDir, null, 'Duplicate');

        // Create a report for errors.
        $this->createRaportFile($errors, $resultFiles, $resultsDir, null, 'Error');

        $this->logger->notice('Successfully imported {importedMessages} message(s). Duplicate(s): {duplicates}. Error(s): {errors}', [
            'command' => $this->getName(),
            'importedMessages' => count($decodedMessages) - count($duplicates) - count($errors),
            'duplicates' => count($duplicates),
            'errors' => count($errors),
            'resultFiles' => implode(', ', $resultFiles),
            'sourceFilePath' => $filePath,
        ]);

        // Generate a message accordingly to import results (without/with errors, duplicates).
        $this->generateResultMessage($io, $decodedMessages, $reviews, $failureReports, $duplicates, $errors, $resultFiles);

        return Command::SUCCESS;
    }

    /**
     * Checks an error type of broken entity.
     *
     * @param array $message        saves error info to variable
     * @param array $messageEntity  info about broken entity
     * @param array $duplicates     save to show in console
     * @param array $errors         save to show in console
     * @return void                 saves the error
     * @throws \Exception
     */
    private function checkErrorType($message, $messageEntity, &$duplicates, &$errors): void
    {
        match (key($messageEntity)) {
            // If it is duplicate save to specific file.
            'duplicate' => [
                $message['duplicate'] = $messageEntity['duplicate'],
                $duplicates[] = $message
            ],
            'error' => [
                // If it is error save to specific file.
                $message['error'] = $messageEntity['error'],
                $errors[] = $message,
            ],
            default => throw new \Exception('Unexpected match value')
        };
    }

    /**
     * Helper method, which check if file exists.
     *
     * @param string $filePath path of the file to check
     * @return bool if file exists
     */
    private function checkFileExist(string $filePath): bool
    {
        return $this->fileSystem->exists($filePath);
    }

    /**
     * Serialize given entity into JSON format.
     *
     * @throws \JsonException
     */
    private function serializeEntity($entity): mixed
    {
        return json_decode($this->serializer->serialize($entity, JsonEncoder::FORMAT), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Generates result messages accordingly to given data.
     *
     * @param SymfonyStyle $io       to generate console logs
     * @param array $decodedMessages available messages
     * @param array $reviews         generated entities
     * @param array $failureReports  generated entities
     * @param array $duplicates      from database
     * @param array $errors          while entities creation
     * @param array $resultFiles     generated files
     * @return void                  messages in console
     */
    private function generateResultMessage(SymfonyStyle $io, array $decodedMessages, array $reviews, array $failureReports, array $duplicates, array $errors, array $resultFiles): void
    {
        $importedMessages = count($decodedMessages) - count($duplicates) - count($errors);

        // If we don't have new imported items.
        if (empty($importedMessages)) {
            $io->success(sprintf(
                'Total processed messages: %d. Not new messages created. Duplicate(s): %d. Error(s): %d.',
                count($decodedMessages),
                count($duplicates),
                count($errors)
            ));
            $this->generateErrorMessage($io, $errors);
            return;
        }

        // If imported without errors.
        if (empty($errors)) {
            $io->success(sprintf(
                'Total processed messages: %d. Duplicate(s): %d.',
                count($decodedMessages),
                count($duplicates) > 0 ? count($duplicates) : 'none',
            ));
            $io->success(sprintf(
                'Imported %d new message(s). Reviews: %d. Failure Reports: %d.',
                $importedMessages,
                count($reviews),
                count($failureReports),
            ));
            // If imported with errors.
        } else {
            $io->warning(sprintf(
                'Total processed messages: %d. Duplicate(s): %d. Error(s): %d.',
                count($decodedMessages),
                count($duplicates) > 0 ? count($duplicates) : 'none',
                count($errors)
            ));
            $io->success(sprintf(
                'Imported %d new message(s). Reviews: %d. Failure Reports: %d.',
                $importedMessages,
                count($reviews),
                count($failureReports),
            ));

            $this->generateErrorMessage($io, $errors);
        }

        // If we have result files.
        if (!empty($resultFiles)) {
            $io->note(sprintf(
                'Result Files: %s.',
                implode(', ', $resultFiles),
            ));
        } else {
            $io->note("Not any result files generated.");
        }
    }

    /**
     * Generates error messages accordingly to given data.
     *
     * @param SymfonyStyle $io to generate console errors
     * @param array $errors    available errors
     * @return void            created errors
     */
    private function generateErrorMessage(SymfonyStyle $io, array $errors): void
    {
        foreach ($errors as $error) {
            $io->error(sprintf(
                'Number: %d. Error: %s',
                $error['number'],
                $error['error']
            ));
        }
    }

    /**
     * Creates raport file in JSON format accordingly to given data.
     *
     * @param array $reports that should exist in raport
     * @param array $resultFiles to show in console
     * @param string $resultsDir in which save raport
     * @param string|null $class not required, used for file name
     * @param string|null $name not required, used for file name
     * @return void               generate files and adds a created name to $resultFiles
     * @throws \JsonException
     */
    private function createRaportFile(array $reports, array &$resultFiles, string $resultsDir, string $class = null, string $name = null): void
    {
        // Create a report for Review message type.
        if ($name === null && $class !== null) {
            $className = $this->getClassShortName($class);
        } else {
            $className = $name;
        }

        if (!empty($reports)) {
            try {
                $json = json_encode($reports, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            } catch (JsonException $e) {
                $this->logger->error('Error while encoding {class} class entities.', [
                    'class' => $class,
                    'exception' => $e,
                ]);
            }
            $date = new \DateTime();
            $fileShortName = $className . 's_' . $date->format('d_m_Y_H_i_s') . '.json';
            // Create unique filename.
            $fileName = $resultsDir .  '/' . $fileShortName;
            // Create a file with results.
            $this->fileSystem->touch($fileName);
            $this->fileSystem->dumpFile($fileName, $json);

            $resultFiles[] = $fileShortName;
        }
    }

    /**
     * Generates short name for class instance.
     *
     * @param string $class for which create short name
     * @return string         short name for class
     * @throws \ReflectionException
     */
    private function getClassShortName(string $class): string
    {
        return (new \ReflectionClass($class))->getShortName();
    }
}
