<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\DataImporterBundle\Command;

use Doctrine\DBAL\Driver\Exception;
use Pimcore\Bundle\DataImporterBundle\Exception\InvalidConfigurationException;
use Pimcore\Bundle\DataImporterBundle\Processing\ImportProcessingService;
use Pimcore\Bundle\DataImporterBundle\Queue\QueueService;
use Pimcore\Console\AbstractCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

class SequentialProcessQueueCommand extends AbstractCommand
{
    /**
     * @var ImportProcessingService
     */
    protected $importProcessingService;

    /**
     * @var QueueService
     */
    protected $queueService;

    /**
     * @var LockInterface|null
     */
    private $lock;

    public function __construct(ImportProcessingService $importProcessingService, QueueService $queueService)
    {
        parent::__construct();
        $this->importProcessingService = $importProcessingService;
        $this->queueService = $queueService;
    }

    public function configure()
    {
        $this
            ->setName('datahub:data-importer:process-queue-sequential')
            ->setDescription('Processes all items of the queue that need to be executed sequential.')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|void
     *
     * @throws Exception
     * @throws \Doctrine\DBAL\Exception
     * @throws InvalidConfigurationException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->lock()) {
            $this->writeError('The command is already running.');
            exit(1);
        }

        try {
            $itemIds = $this->queueService->getAllQueueEntryIds(ImportProcessingService::EXECUTION_TYPE_SEQUENTIAL);
            $itemCount = count($itemIds);

            $output->writeln("Processing {$itemCount} items sequentially\n");

            $progressBar = new ProgressBar($output, $itemCount);
            $progressBar->start();

            foreach ($itemIds as $i => $id) {
                $this->importProcessingService->processQueueItem($id);
                $progressBar->advance();

                // call the garbage collector to avoid too many connections & memory issue
                if (($i + 1) % 200 === 0) {
                    \Pimcore::collectGarbage();
                }
            }

            $progressBar->finish();

            $this->release(); //release the lock

            $output->writeln("\n\nProcessed {$itemCount} items.");

            return 0;
        } catch (\Throwable $t) {
            $this->release();
            throw $t;
        }
    }

    /**
     * Locks the command.
     *
     * @return bool
     */
    private function lock(): bool
    {
        $this->lock = \Pimcore::getContainer()->get(LockFactory::class)->createLock($this->getName(), 86400);

        if (!$this->lock->acquire(false)) {
            $this->lock = null;

            return false;
        }

        return true;
    }

    /**
     * Releases the command lock if there is one.
     */
    private function release()
    {
        if ($this->lock) {
            $this->lock->release();
            $this->lock = null;
        }
    }
}
