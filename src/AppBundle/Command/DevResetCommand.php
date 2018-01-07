<?php

/*
 * This file is part of the Kimai package.
 *
 * (c) Kevin Papst <kevin@kevinpapst.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command used to execute all the basic application bootstrapping AFTER "composer install" was executed.
 *
 * @author Kevin Papst <kevin@kevinpapst.de>
 */
class DevResetCommand extends Command
{

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('kimai:dev:reset')
            ->setDescription('Resets the dev environment')
            ->setHelp(<<<EOT
    This command will drop and re-create the database and its schemas, load development fixtures and clear the cache.
    Use the <info>-n</info> switch to skip the question.
EOT
            )
            ->addOption('no-cache', null, InputOption::VALUE_NONE, 'Skip cache flushing')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->isInteractive()) {
            if (!$this->askConfirmation(
                $input,
                $output,
                '<question>Careful, database will be purged. Do you want to continue y/N ?</question>',
                false
            )) {
                return;
            }
        }

        $command = $this->getApplication()->find('doctrine:database:create');
        try {
            $command->run(new ArrayInput([]), $output);
        } catch (\Exception $ex) {
            $io->error('Failed to create database: ' . $ex->getMessage());
            return 1;
        }

        $command = $this->getApplication()->find('doctrine:schema:drop');
        try {
            $command->run(new ArrayInput(['--force' => true]), $output);
        } catch (\Exception $ex) {
            $io->error('Failed to drop database schema: ' . $ex->getMessage());
            return 2;
        }

        $command = $this->getApplication()->find('doctrine:schema:create');
        try {
            $command->run(new ArrayInput([]), $output);
        } catch (\Exception $ex) {
            $io->error('Failed to create database schema: ' . $ex->getMessage());
            return 3;
        }

        $command = $this->getApplication()->find('doctrine:fixtures:load');
        try {
            $cmdInput = new ArrayInput([]);
            $cmdInput->setInteractive(false);
            $command->run($cmdInput, $output);
        } catch (\Exception $ex) {
            $io->error('Failed to import fixtures: ' . $ex->getMessage());
            return 4;
        }

        if ($input->getOption('no-cache')) {
            return 0;
        }

        $command = $this->getApplication()->find('cache:clear');
        try {
            $command->run(new ArrayInput([]), $output);
        } catch (\Exception $ex) {
            $io->error('Failed to clear cache: ' . $ex->getMessage());
            return 5;
        }

        return 0;
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param string $question
     * @param bool $default
     * @return bool
     */
    private function askConfirmation(InputInterface $input, OutputInterface $output, $question, $default)
    {
        $questionHelper = $this->getHelperSet()->get('question');
        $question = new ConfirmationQuestion($question, $default);

        return $questionHelper->ask($input, $output, $question);
    }
}
