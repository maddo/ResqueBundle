<?php

namespace ShonM\ResqueBundle\Command;

use Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface,
    Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand,
    ReflectionClass,
    RuntimeException;

class JobEnqueueCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('resque:job:enqueue')
             ->setDescription('Enqueue a job')
             ->addArgument('class', InputArgument::REQUIRED, 'Full qualified class name')
             ->addArgument('queue', InputArgument::OPTIONAL, 'Queue name', '*')
             ->addArgument('arguments', InputArgument::OPTIONAL, 'JSON arguments for the job', null)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $class = new ReflectionClass($input->getArgument('class'));
        $args = json_decode($input->getArgument('arguments'), true);

        if ($input->getArgument('arguments') && ! $args) {
            throw new RuntimeException('Arguments could not be properly decoded (try putting them in single quotes)');
        }

        $this->getContainer()->get('resque')->add($class->getName(), $input->getArgument('queue'), $args ?: array());
    }
}
