<?php
declare(strict_types=1);
namespace Wwwision\ImportService\Command;

use Neos\Error\Messages\Message;
use Neos\Error\Messages\Result;
use Neos\Flow\Cli\CommandController;
use Wwwision\ImportService\ImportService;
use Wwwision\ImportService\ImportServiceException;
use Wwwision\ImportService\Factory\ImportServiceFactory;
use Wwwision\ImportService\ValueObject\ChangeSet;
use Wwwision\ImportService\ValueObject\DataIds;
use Wwwision\ImportService\ValueObject\DataRecords;

final class ImportCommandController extends CommandController
{

    public function __construct(
        private readonly ImportServiceFactory $importServiceFactory,
    ) {
        parent::__construct();
    }

    /**
     * @internal
     * @deprecated use {@see self::runCommand} instead
     */
    public function importCommand(string $preset, ?bool $quiet = null, ?bool $forceUpdates = null, ?bool $fromFixture = null): void
    {
        $this->runCommand($preset, $quiet, $forceUpdates, $fromFixture);
    }

    /**
     * Synchronizes data (add, update, delete) for the given preset
     *
     * @param string $preset The preset to use (see Wwwision.Import.presets setting)
     * @param bool|null $quiet If set, no output, apart from errors, will be displayed
     * @param bool|null $forceUpdates If set, all local records will be updated regardless of their version/timestamp. This is useful for node type changes that require new data to be fetched
     * @param bool|null $fromFixture If set, the data will be loaded from a local fixture file instead of the configured data source
     */
    public function runCommand(string $preset, ?bool $quiet = null, ?bool $forceUpdates = null, ?bool $fromFixture = null): void
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
        }

        try {
            $numberOfRemovedRecords = $importService->removeAllData();
        } catch (ImportServiceException $exception) {
            $this->outputLine(\chr(10) . '<error>Error</error>');
            $this->outputLine('%s (%s)', [$exception->getMessage(), $exception->getCode()]);
            $this->quit(1);
        }

        if ($quiet !== true) {
            $this->outputLine('Removed <b>%d</b> record(s)!', [$numberOfRemovedRecords]);
        }
    }

    /**
     * Lists all configured preset names
     */
    public function presetsCommand(): void
    {
        $presetNames = $this->importServiceFactory->getPresetNames();
        if ($presetNames === []) {
            $this->outputLine('<error>There are no import presets defined</error>');
            $this->quit();
        }
        $presetCount = \count($presetNames);
        if ($presetCount === 1) {
            $this->outputLine('The following preset is defined:');
        } else {
            $this->outputLine('The following <b>%d</b> presets are defined:', [$presetCount]);
        }
        foreach ($presetNames as $presetName) {
            $this->outputLine(' * <b>%s</b>', [$presetName]);
        }
    }

    /**
     * Displays configuration for a given preset
     */
    public function presetCommand(string $preset): void
    {
        $presetConfiguration = $this->importServiceFactory->getPresetConfiguration($preset);
        $this->outputLine('<b>Source</b>');
        $rows = [
            ['factory', $presetConfiguration['source']['factory']],
        ];
        foreach ($presetConfiguration['source']['options'] ?? [] as $optionKey => $optionValue) {
            $rows[] = [$optionKey, $optionValue];
        }
        $this->output->outputTable($rows, ['Option', 'Value']);

        $this->outputLine('<b>Target</b>');
        $rows = [
            ['factory', $presetConfiguration['target']['factory']],
        ];
        foreach ($presetConfiguration['target']['options'] ?? [] as $optionKey => $optionValue) {
            $rows[] = [$optionKey, $optionValue];
        }
        $this->output->outputTable($rows, ['Option', 'Value']);

        $this->outputLine('<b>Mapping</b>');
        $rows = [];
        foreach ($presetConfiguration['mapping'] ?? [] as $attributeName => $mappingValue) {
            $rows[] = [$attributeName, $mappingValue];
        }
        $this->output->outputTable($rows, ['Source attribute', 'Mapping']);
    }

    /**
     * Set up the configured data source and target for the specified preset and/or display status
     *
     * @param string $preset Name of the preset to set up
     */
    public function setupCommand(string $preset): void
    {
        $setupResult = $this->importServiceFactory->create($preset)->setUp();
        $this->renderResult($setupResult);
        if ($setupResult->hasErrors() || $setupResult->hasWarnings()) {
            $this->quit(1);
        }
        $this->outputLine('<success>Setup finalized without errors or warnings</success>');
    }

    // ---------------------------------------

    private function renderResult(Result $result): void
    {
        array_map(fn (Message $message) => $this->outputLine('<error>%s</error>', [$message->render()]), $result->getErrors());
        array_map(fn (Message $message) => $this->outputLine('<comment>%s</comment>', [$message->render()]), $result->getWarnings());
        array_map(fn (Message $message) => $this->outputLine('<success>%s</success>', [$message->render()]), $result->getNotices());
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
        $importService->on(ImportService::EVENT_PRE_COMPUTE_CHANGES, function(DataRecords $records) {
            $this->outputLine('Loaded <b>%d</b> records, calculating diff …', [$records->count()]);
        });
        $importService->on(ImportService::EVENT_PRE_IMPORT_DATA, function(ChangeSet $changeSet) {
            $this->outputLine('<b>%s</b> to add, <b>%s</b> to update and <b>%s</b> to remove …', [$changeSet->addedRecords->count(), $changeSet->updatedRecords->count(), $changeSet->removedIds->count()]);
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
