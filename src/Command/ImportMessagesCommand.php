<?php

namespace App\Command;

use App\Entity\Message\FailureReport;
use App\Entity\Message\Review;
use App\Service\MessageService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpKernel\KernelInterface;

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

    public function __construct(private Filesystem $fileSystem, private MessageService $messageService, private EntityManagerInterface $entityManager, private KernelInterface $kernel,private LoggerInterface $logger)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('filePath', InputArgument::REQUIRED, 'Path of the source file');
    }

    /**
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

        $resultFiles = [];
        $reviews = [];
        $failureReports = [];
        $duplicates = [];
        $errors = [];
        // Decode the message from JSON format.
        try {
            $decodedMessages = json_decode($encodedMessages, true, 512, JSON_THROW_ON_ERROR);
            $this->logger->info('Started import of messages from source.', [
                'command' => $this->getName(),
                'filePath' => $filePath,
                'file_format' => 'json',
            ]);
            foreach ($decodedMessages as $message) {
                $messageEntity = $this->messageService->createMessage($message);

                // Check if duplicate.
                if (is_string($messageEntity)) {
                    match ($messageEntity) {
                        'duplicate' => $duplicates[] = $message['number'],
                        'error' => $errors[] = $message['number'],
                    };
                    break;
                }
                // Save entities (not yet in a database).
                $this->entityManager->persist($messageEntity);
            }
        } catch (JsonException $e) {
            $io->error(sprintf('JSON is broken in file: %s', $filePath));
            $this->logger->warning('JSON file decoding failed.', [
                'command' => $this->getName(),
                'filePath' => $filePath,
                'exception' => $e
            ]);
        }
        // Query all added entities.
        $this->entityManager->flush();

        // Create a directory for results.
        $resultsDir = $this->kernel->getProjectDir() . '/results';

//        if ($this->fileSystem->exists($resultsDir) === false) {
            $this->fileSystem->mkdir($resultsDir);
//        }

        foreach (self::MESSAGE_TYPES as $class) {
            $classEntities = $this->entityManager->getRepository($class)->findAll();
            $classReflection = new \ReflectionClass($class);
            $className = $classReflection->getShortName();

            if (!empty($classEntities)) {
                try {
                    $json = json_encode($classEntities, JSON_THROW_ON_ERROR);
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

        $this->logger->notice('Successfully imported {importedMessages} message(s). Duplicate(s): {duplicates}. Error(s): {errors}', [
            'command' => $this->getName(),
            'importedMessages' => count($decodedMessages) - count($duplicates) - count($errors),
            'duplicates' => count($duplicates),
            'errors' => count($errors),
            'resultFiles' => ['/first', '/second'],
            'sourceFilePath' => $filePath,
        ]);

        $io->success(sprintf('Your entities is ready! You can check the results folder in: %s', $resultsDir));
        $io->success(sprintf(
            'Successfully imported %d message(s). Duplicate(s): %d. Error(s): %d. Command: %s. Result Files: %s. Source File Path: %s',
            count($decodedMessages) - count($duplicates) - count($errors),
            count($duplicates),
            count($errors),
            $this->getName(),
            implode(', ', $resultFiles),
            $filePath
        ));

        return Command::SUCCESS;
    }

    /**
     *  Helper method, which check if file exists.
     *
     * @param string $filePath path of the file to check
     * @return bool if file exists
     */
    private function checkFileExist(string $filePath): bool
    {
        return $this->fileSystem->exists($filePath);
    }
}
