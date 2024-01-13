<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpKernel\Log\Logger;

#[AsCommand(
    name: 'app:import-messages',
    description: 'Imports messages as entities from JSON type source file. Outputs the final results',
    aliases: ['app:im, aim']
)]
class ImportMessagesCommand extends Command
{
    public function __construct(private Filesystem $fileSystem, private Logger $logger)
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

        // Check if source file exist.
        if (!$this->checkFileExist($filePath)) {
            $io->error(sprintf('Provided filepath to the source is invalid: %s.', $filePath));
            $this->logger->warning('Provided filepath to the source is invalid.', [
                'command' => $this->getName(),
                'filePath' => $filePath,
            ]);
            return Command::INVALID;
        }

        // Check if source file readable.
        if (!($encodedMessages = file_get_contents($filePath)) && json_validate($encodedMessages)) {
            $io->error(sprintf('Cannot read contents of source file or JSON is incorrect: %s', $filePath));
            $this->logger->warning('File is empty or invalidate JSON on import.', [
                'command' => $this->getName(),
                'filePath' => $filePath,
            ]);
        }

        try {
            $decodedMessages = json_decode($encodedMessages, true, 512, JSON_THROW_ON_ERROR);
            $this->logger->info('Started import of messages from source.', [
                'command' => $this->getName(),
                'filePath' => $filePath,
                'file_format' => 'json',
            ]);

            foreach ($decodedMessages as $message) {

            }

        } catch (JsonException $e) {
            $io->error(sprintf('JSON is broken in file: %s', $filePath));
            $this->logger->warning('JSON file decoding failed.', [
                'command' => $this->getName(),
                'filePath' => $filePath,
                'exception' => $e
            ]);
        }

        $io->success(sprintf('Your entities is ready! You can check the results folder in: %s.', 'results/files'));

        $this->logger->info('Finished messages import. New imported: {importedCount}. Duplicates: {duplicatesCount}. Errors: {errorsCount}.', [
            'command' => $this->getName(),
            'filePath' => $filePath,
            'importedCount' => 1,
            'duplicatesCount' => 1,
            'errorsCount' => 1,
            'resultFiles' => ['/first', '/second']
        ]);


        return Command::SUCCESS;
    }

    /**
     *  Helper method, which check if file exists.
     *
     * @param string $filePath path of the file to check
     * @return bool if file exist
     */
    protected function checkFileExist(string $filePath): bool
    {
        return $this->fileSystem->exists($filePath);
    }
}
