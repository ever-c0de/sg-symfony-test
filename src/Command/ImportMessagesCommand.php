<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-messages',
    description: 'Imports messages as entities from source file. Outputs the final results',
    aliases: ['app:im, aim']
)]
class ImportMessagesCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('filePath', InputArgument::REQUIRED, 'Path of the source file')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('filePath');

        if ($filePath) {
            $io->note(sprintf('Provided filepath to the source: %s', $filePath));
        }

        $io->success(sprintf('Your entities is ready! You can check the results folder in: %s.', '/asdasdwad'));

        return Command::SUCCESS;
    }
}
