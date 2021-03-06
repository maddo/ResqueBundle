<?php

namespace ShonM\ResqueBundle\Command;

use Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface,
    Symfony\Component\Process\Process,
    Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand,
    ShonM\ResqueBundle\Worker,
    InvalidArgumentException,
    RuntimeException,
    Resque_Worker;

class WorkerSignalCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('resque:worker:signal')
             ->setDescription('Signal workers (shutdown, restart, pause, and resume)')
             ->addArgument('worker', InputArgument::OPTIONAL, 'Worker name')
             ->addOption('signal', null, InputOption::VALUE_OPTIONAL, 'Signal [QUIT|TERM|INT|USR1|USR2|CONT]', 'QUIT')
             ->addOption('all', null, InputOption::VALUE_NONE, 'Signal all workers')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $resque = $this->getContainer()->get('resque');

        // Prune dead workers first
        $worker = new Resque_Worker('*');
        $worker->pruneDeadWorkers();

        // Now find all existing workers
        $workers = $resque->workers();

        if (! $workers) {
            return $output->writeln('<error>There are no available workers</error>');
        }

        $descriptions = array(
            'QUIT'     => 'Wait for child to finish processing then exit',
            'TERM|INT' => 'Immediately kill child then exit',
            'USR1'     => 'Immediately kill child but don\'t exit',
            'USR2'     => 'Pause worker, no new jobs will be processed',
            'CONT'     => 'Resume worker',
        );

        if (! in_array($input->getOption('signal'), array_keys($descriptions))) {
            throw new InvalidArgumentException('The signal "' . $input->getOption('signal') . '" is not in the list of available signals');
        }

        $output->writeln('Signal: <comment>' . $input->getOption('signal') . '</comment>');
        $output->writeln('');

        // If there was a specific worker passed, check for it
        if ($target = $input->getArgument('worker')) {
            foreach ($workers as $worker) {
                if ($target == (string) $worker) {
                    return $this->signal($worker, $input->getOption('signal'));
                }
            }

            return $output->writeln('<error>The worker specified could not be found</error>');
        }

        // If the all option was passed, just straight up signal everything
        if ($input->getOption('all')) {
            foreach ($workers as $worker) {
                $this->signal($worker, $input->getOption('signal'));
            }
        } else {
            // Check if dialog helper supports choices, which makes things much easier for the user
            $dialog = $this->getHelper('dialog');
            if (method_exists($dialog, 'select')) {
                $pick = $dialog->select($output, 'Select a worker to signal:', $workers);
                $this->signal($workers[$pick], $input->getOption('signal'));
            } else {
                // Fall back to copy paste :(
                $output->writeln('Active workers (copy and paste to signal):');
                foreach ($workers as $worker) {
                    $output->writeln('    ' . $worker);
                }
            }
        }
    }

    private function signal(Worker $worker, $signal = 'QUIT')
    {
        list($machine, $process, $queue) = explode(':', $worker->getId());

        $process = new Process('kill -' . $signal . ' ' . $process);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException($process->getErrorOutput());
        }

        $this->output->writeln($worker->getId() . ' signaled');
    }
}
