<?php
declare(strict_types=1);
namespace Wwwision\ImportService\Command;

use Wwwision\ImportService\ImportService;
use Wwwision\ImportService\ImportServiceException;
use Wwwision\ImportService\ImportServiceFactory;
use Wwwision\ImportService\ValueObject\ChangeSet;
use Wwwision\ImportService\ValueObject\DataIds;
use Wwwision\ImportService\ValueObject\DataRecords;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Mvc\Exception\StopActionException;

final class ImportCommandController extends CommandController
{

    /**
     * @Flow\Inject
     * @var ImportServiceFactory
     */
    protected $importServiceFactory;

    /**
     * Synchronizes data (add, update, delete) for the given preset
     *
     * @param string $preset The preset to use (see Wwwision.Import.presets setting)
     * @param bool|null $quiet If set, no output, apart from errors, will be displayed
     * @param bool|null $forceUpdates If set, all local records will be updated regardless of their version/timestamp. This is useful for node type changes that require new data to be fetched
     * @param bool|null $fromFixture If set, the data will be loaded from a local fixture file instead of the configured data source
     * @throws StopActionException
     */
    public function importCommand(string $preset, ?bool $quiet = null, ?bool $forceUpdates = null, ?bool $fromFixture = null): void
    {
        if ($fromFixture === true) {
            $importService = $this->importServiceFactory->createWithFixture($preset);
        } else {
            $importService = $this->importServiceFactory->create($preset);
        }

        $this->registerEventHandlers($importService, $quiet ?? false);
        try {
            $importService->importData($forceUpdates ?? false);
        } catch (ImportServiceException $exception) {
            $this->outputLine(\chr(10) . '<error>Import failed</error>');
            $this->outputLine('%s (%s)', [$exception->getMessage(), $exception->getCode()]);
            $this->quit(1);
        }
    }

    /**
     * Removes all data for the given preset
     *
     * @param string $preset The preset to reset (see Wwwision.Import.presets setting)
     * @param bool|null $quiet If set, no output, apart from errors, will be displayed
     * @param bool|null $assumeYes If set, "yes" will be assumed for the confirmation question
     * @throws StopActionException
     */
    public function pruneCommand(string $preset, ?bool $quiet = null, ?bool $assumeYes = null): void
    {
        $importService = $this->importServiceFactory->create($preset);
        $this->registerEventHandlers($importService, $quiet ?? false);
        if ($assumeYes !== true && !$this->output->askConfirmation(sprintf('<error>Are you sure you want to delete all local records for preset "%s" (y/n)?</error>', $preset), false)) {
            if ($quiet !== true) {
                $this->outputLine('...cancelled');
            }
            $this->quit();
            return;
        }

        try {
            $numberOfRemovedRecords = $importService->removeAllData();
        } catch (ImportServiceException $exception) {
            $this->outputLine(\chr(10) . '<error>Error</error>');
            $this->outputLine('%s (%s)', [$exception->getMessage(), $exception->getCode()]);
            $this->quit(1);
            return;
        }

        if ($quiet !== true) {
            $this->outputLine('Removed <b>%d</b> record(s)!', [$numberOfRemovedRecords]);
        }
    }

    /**
     * @param ImportService $importService
     * @param bool $quiet
     */
    private function registerEventHandlers(ImportService $importService, bool $quiet): void
    {
        $importService->on(ImportService::EVENT_ERROR, function(string $errorMessage) {
            $this->outputFormatted('<error>%s</error>', [$errorMessage]);
        });
        if ($quiet) {
            return;
        }
        $importService->on(ImportService::EVENT_PRE_IMPORT_DATA, function(ChangeSet $changeSet) {
            $this->outputLine('<b>%s</b> to add, <b>%s</b> to update and <b>%s</b> to remove …', [$changeSet->addedRecords()->count(), $changeSet->updatedRecords()->count(), $changeSet->removedIds()->count()]);
        });

        $importService->on(ImportService::EVENT_PRE_ADD_DATA, function(DataRecords $addedRecords) {
            $this->outputLine();
            $this->outputLine('<b>Adding …</b>');
            $this->outputLine();
            $this->output->progressStart($addedRecords->count());
        });
        $importService->on(ImportService::EVENT_ADD_DATA, function() {
            $this->output->progressAdvance();
        });
        $importService->on(ImportService::EVENT_POST_ADD_DATA, function() {
            $this->output->progressFinish();
            $this->outputLine();
            $this->outputLine(' <success>Done</success>');
        });

        $importService->on(ImportService::EVENT_PRE_UPDATE_DATA, function(DataRecords $updatedRecords, bool $forced) {
            $this->outputLine();
            $this->outputLine('<b>Updating%s …</b>', [$forced ? ' (forced)' : '']);
            $this->outputLine();
            $this->output->progressStart($updatedRecords->count());
        });
        $importService->on(ImportService::EVENT_UPDATE_DATA, function() {
            $this->output->progressAdvance();
        });
        $importService->on(ImportService::EVENT_POST_UPDATE_DATA, function() {
            $this->output->progressFinish();
            $this->outputLine();
            $this->outputLine(' <success>Done</success>');
        });

        $importService->on(ImportService::EVENT_PRE_REMOVE_DATA, function(DataIds $removedIds) {
            $this->outputLine();
            $this->outputLine('<b>Removing …</b>');
            $this->outputLine();
            $this->output->progressStart($removedIds->count());
        });
        $importService->on(ImportService::EVENT_REMOVE_DATA, function() {
            $this->output->progressAdvance();
        });
        $importService->on(ImportService::EVENT_POST_REMOVE_DATA, function() {
            $this->output->progressFinish();
            $this->outputLine();
            $this->outputLine(' <success>Done</success>');
        });

        $importService->on(ImportService::EVENT_ERROR, function() {
            $this->outputLine();
        });
    }
}
