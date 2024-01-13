<?php

namespace App\Command;

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
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\Exception\JsonException;

#[AsCommand(
    name: 'app:import-messages',
    description: 'Imports messages as entities from JSON type source file. Outputs the final results',
    aliases: ['app:im, aim']
)]
class ImportMessagesCommand extends Command
{
    public function __construct(private Filesystem $fileSystem, private MessageService $messageService, private EntityManagerInterface $entityManager, private LoggerInterface $logger)
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

        // Decode the message from JSON format.
        try {
            $decodedMessages = json_decode($encodedMessages, true, 512, JSON_THROW_ON_ERROR);
            $this->logger->info('Started import of messages from source.', [
                'command' => $this->getName(),
                'filePath' => $filePath,
                'file_format' => 'json',
            ]);

            $reviews = [];
            $failureReports = [];
            $duplicates = [];
            $errors = [];

            foreach ($decodedMessages as $message) {
                $messageEntity = $this->messageService->createMessage($message);

                // Check if duplicate.
                if (is_string($messageEntity)) {
                    match ($messageEntity) {
                        'duplicate' => $duplicates[$message['number']],
                        'error' => $errors[$message['number']],
                    };
                }
            }
        } catch (JsonException $e) {
            $io->error(sprintf('JSON is broken in file: %s', $filePath));
            $this->logger->warning('JSON file decoding failed.', [
                'command' => $this->getName(),
                'filePath' => $filePath,
                'exception' => $e
            ]);
        }

        // Generate result files with entities.
        $classes = $this->getAvailableEntityMessagesTypes();
        // Create a directory for results.
        if ($this->fileSystem->exists(__DIR__ . '../../../results') !== false) {
            $this->fileSystem->mkdir(__DIR__ . '../../../results');
        }
        foreach ($classes as $className) {
            $classEntities = $this->entityManager->getRepository($className)->findAll();
            if (!empty($classEntities)) {
                try {
                    $json = json_encode($classEntities, JSON_THROW_ON_ERROR);
                    $io->success(sprintf('Your entities is ready! JSON: %s.', (string) $json));
                } catch (JsonException $e) {
                    $this->logger->error('Error while encoding {class} class entities.', [
                        'class' => $className,
                        'exception' => $e,
                    ]);
                }
                $date = new \DateTime();
                // Create unique filename.
                $fileName = $className . 's_' . \DateTime::createFromFormat('d_m_Y_H_i_s', $date->getTimestamp());
                // Create a file with results.
                $this->fileSystem->dumpFile($fileName, $json);
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

        $io->success(sprintf('Your entities is ready! You can check the results folder in: %s.', 'results/files'));

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

    private function getAvailableEntityMessagesTypes()
    {
        $classNames = [];
        // Path to the folder where classes located.
        $directoryPath = __DIR__ . '/../Entity/Message';

        // Using Symfony Finder to find classes.
        $finder = new Finder();
        $finder->files()->in($directoryPath)->name('*.php');

        foreach ($finder as $file) {
            // Get the class name from the file.
            if (!empty($name = $this->getClassNameFromFile($file))) {
                $classNames[] = $name;
            }
        }

        return $classNames;
    }

    // Helper method to get the class name from a file.
    private function getClassNameFromFile(SplFileInfo $file): string
    {
        // Get the content of the file
        $fileContent = $file->getContents();

        // Use a regular expression to find the class.
        $pattern = '/class (\w+).*\{/';
        preg_match($pattern, $fileContent, $matches);

        // Return the class name (if found).
        return $matches[1] ?? '';
    }
}
