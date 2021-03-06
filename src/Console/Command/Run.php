<?php
/**
 * Init.php
 * @author    Daniel Mason <daniel.mason@thefoundry.co.uk>
 * @copyright 2015 The Foundry Visionmongers
 * @license
 * @see       https://github.com/TheFoundryVisionmongers/Masonry
 */

namespace Foundry\Masonry\Console\Command;

use Foundry\Masonry\Console\Exception\FileExistsException;
use Foundry\Masonry\Core\CoroutineRegister;
use Foundry\Masonry\Core\GlobalRegister;
use Foundry\Masonry\Core\Task;
use Foundry\Masonry\ModuleRegister\ModuleRegister;
use Foundry\Masonry\Workers\Group\Serial\Description;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class Init
 * Initialise Masonry in the current directory with a masonry.yaml
 * @package Masonry
 * @see     https://github.com/TheFoundryVisionmongers/Masonry
 */
class Run extends AbstractCommand
{

    /**
     * Set up command
     * @return void
     */
    protected function configure()
    {
        $this->abstractConfigure('run', 'Runs the currently configured masonry config.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws FileExistsException
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = $this->setUpLogger($output);
        GlobalRegister::setLogger($logger);

        // Should be able to specify a different module registry?
        $logger->info("Setting up module register");
        $moduleRegister = ModuleRegister::load();
        GlobalRegister::setModuleRegister($moduleRegister);

        // Get the queue file
        $logger->info("Loading queue");
        $filesystem = $this->getFilesystem();
        $queueFile = $this->getQueueFullPath($input);
        if (!$filesystem->exists($queueFile)) {
            throw new FileExistsException("File '{$queueFile}' doesn't exist, run 'masonry init' to create one");
        }

        // Process the pool
        $logger->info("Processing queue");
        $taskArray = $this->readYamlFile($queueFile);
        $mediator = GlobalRegister::getMediator();
        $coroutine = $mediator->process(
            new Task(
                new Description($taskArray)
            )
        );
        $coroutineRegister = new CoroutineRegister();
        $coroutineRegister->register($coroutine);
        while ($coroutineRegister->isValid()) {
            $coroutineRegister->tick();
        }

        $logger->info('Done');
    }

    /**
     * @param $file
     * @return array
     */
    protected function readYamlFile($file)
    {
        return $taskArray = (array)Yaml::parse(file_get_contents($file));
    }
}
