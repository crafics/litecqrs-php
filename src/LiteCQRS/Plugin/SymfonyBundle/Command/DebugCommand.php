<?php

namespace LiteCQRS\Plugin\SymfonyBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

class DebugCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('lite-cqrs:debug')
            ->setDescription('Display currently registered commands and events.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $builder = $this->getContainerBuilder();

        $maxName        = 0;
        $maxId          = 0;
        $maxCommandType = 0;

        foreach ($container->findTaggedServiceIds('lite_cqrs.command_handler') as $id => $attributes) {
            $definition = $container->findDefinition($id);
            $class = $definition->getClass();

            $reflClass = new \ReflectionClass($class);
            foreach ($reflClass->getMethods() as $method) {
                if ($method->getNumberOfParameters() != 1) {
                    continue;
                }

                $commandParam = current($method->getParameters());

                if (!$commandParam->getClass() || !in_array('LiteCQRS\Command', class_implements($commandParam->getClass()->getName()))) {
                    continue;
                }

                $commandType = $commandParam->getClass()->getName();
                $parts = explode("\\", $commandType);
                $name = end($commandType);

                $commands[$commandType] = array('name' => $name, 'id'  => $id, 'class' => $class);

                $maxName        = max(strlen($name), $maxName);
                $maxId          = max(strlen($id), $maxId);
                $maxCommandType = max(strlen($commandType), $maxCommandType);
            }
        }

        $output->writeln('Commands');
        $output->writeln('========');

        $format  = '%-'.$maxName.'s %-'.$maxId.'s %s';

        // the title field needs extra space to make up for comment tags
        $format1  = '%-'.($maxName + 19).'s %-'.($maxId + 19).'s %s';
        $output->writeln(sprintf($format1, '<comment>Command</comment>', '<comment>Service</comment>', '<comment>Class</comment>'));

        foreach ($commands as $type => $command) {
            $output->writeln(sprintf($format, $command['type'], $command['id'], $type));
        }

        $events         = array();
        $maxName        = 0;
        $maxId          = 0;
        $maxEventName   = 0;

        foreach ($container->findTaggedServiceIds('lite_cqrs.event_handler') as $id => $attributes) {
            $definition = $container->findDefinition($id);
            $class = $definition->getClass();

            $reflClass = new \ReflectionClass($class);
            foreach ($reflClass->getMethods() as $method) {
                if ($method->getNumberOfParameters() != 1) {
                    continue;
                }

                $methodName = $method->getName();
                if (strpos($methodName, "on") !== 0) {
                    continue;
                }

                $eventName = strtolower(substr($methodName, 2));

                if (!isset($services[$eventName])) {
                    $services[$eventName] = array();
                }

                $events[]     = array('eventName' => $eventName, 'id' => $id, 'class' => $class);
                $maxName      = max(strlen($name), $maxName);
                $maxId        = max(strlen($id), $maxId);
                $maxEventName = max(strlen($eventName), $maxEventName);
            }
        }

        $output->writeln('EVENTS');
        $output->writeln('======');

        $format  = '%-'.$maxName.'s %-'.$maxId.'s %s';

        // the title field needs extra space to make up for comment tags
        $format1  = '%-'.($maxName + 19).'s %-'.($maxId + 19).'s %s';
        $output->writeln(sprintf($format1, '<comment>Event</comment>', '<comment>Service</comment>', '<comment>Class</comment>'));

        foreach ($events as $event) {
            $output->writeln(sprintf($format, $event['eventName'], $event['id'], $event['class']));
        }
    }

    /**
     * Loads the ContainerBuilder from the cache.
     *
     * @return ContainerBuilder
     */
    private function getContainerBuilder()
    {
        if (!$this->getApplication()->getKernel()->isDebug()) {
            throw new \LogicException(sprintf('Debug information about the container is only available in debug mode.'));
        }

        if (!file_exists($cachedFile = $this->getContainer()->getParameter('debug.container.dump'))) {
            throw new \LogicException(sprintf('Debug information about the container could not be found. Please clear the cache and try again.'));
        }

        $container = new ContainerBuilder();

        $loader = new XmlFileLoader($container, new FileLocator());
        $loader->load($cachedFile);

        return $container;
    }
}
