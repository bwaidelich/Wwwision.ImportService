<?php
declare(strict_types=1);
namespace Wwwision\ImportService;

use Neos\Error\Messages\Result;
use Wwwision\ImportService\Factory\ImportServiceFactory;
use Wwwision\ImportService\ValueObject\DataIds;
use Wwwision\ImportService\ValueObject\DataRecords;

/**
 * The central authority for importing data.
 * @see ImportServiceFactory for the corresponding factory
 */
final class ImportService
{

    public const EVENT_ERROR = 'error';

    public const EVENT_PRE_COMPUTE_CHANGES = 'preComputeChanges';
    public const EVENT_PRE_IMPORT_DATA = 'preImportData';
    public const EVENT_PRE_ADD_DATA = 'preAddData';
    public const EVENT_ADD_DATA = 'addData';
    public const EVENT_POST_ADD_DATA = 'postAddData';

    public const EVENT_PRE_UPDATE_DATA = 'preUpdateData';
    public const EVENT_UPDATE_DATA = 'updateData';
    public const EVENT_POST_UPDATE_DATA = 'postUpdateData';

    public const EVENT_PRE_REMOVE_DATA = 'preRemoveData';
    public const EVENT_REMOVE_DATA = 'removeData';
    public const EVENT_POST_REMOVE_DATA = 'postRemoveData';

    /**
     * @var array<string, \Closure>
     */
    private array $callbacks = [];

    public function __construct(
        private readonly Preset $preset
    ) {
    }

    /**
     * Register a handler that is invoked if the corresponding event is dispatched.
     * @param string $eventName One of the EVENT_* constants
     * @param \closure $callback
     * @see dispatch()
     */
    public function on(string $eventName, \closure $callback): void
    {
        if (!\array_key_exists($eventName, $this->callbacks)) {
            $this->callbacks[$eventName] = [];
        }
        $this->callbacks[$eventName][] = $callback;
    }

    public function setup(): Result
    {
        $result = new Result();
        $result->merge($this->preset->dataSource->setup());
        $result->merge($this->preset->dataTarget->setup());
        return $result;
    }

    /**
     * Import data of the specified type from the data source to the content repository
     *
     * @param bool $forceUpdates If set, all existing nodes for the respective type will be updated regardless of their last modified timestamp. This is useful for node type changes that require new data to be fetched
     * @throws ImportServiceException Standard exception for when something goes wrong
     */
    public function importData(bool $forceUpdates): void
    {
        $data = $this->preset->load();
        $this->dispatch(self::EVENT_PRE_COMPUTE_CHANGES, $data);
        $changeSet = $this->preset->computeDataChanges($data, $forceUpdates);
        $this->dispatch(self::EVENT_PRE_IMPORT_DATA, $changeSet);
        if ($this->preset->isSkipAddedRecords() && $changeSet->hasDataToAdd()) {
            throw new ImportServiceException('This preset is configured to skip added records, but the data target returned new records. Check your configuration and consider executing migrations', 1528889870);
        }
        if ($this->preset->isSkipRemovedRecords() && $changeSet->hasDataToRemove()) {
            throw new ImportServiceException('This preset is configured to skip removed records, but the data target returned removed records. Check your configuration and consider executing migrations', 1561122090);
        }

        $this->addData($changeSet->addedRecords);
        $this->updateData($changeSet->updatedRecords, $forceUpdates);
        $this->removeData($changeSet->removedIds);
        $this->preset->finalize();
    }

    /**
     * Remove all data nodes from the content repository
     *
     * @return int the number of removed local records
     * @throws ImportServiceException
     */
    public function removeAllData(): int
    {
        if ($this->preset->isSkipAddedRecords() || $this->preset->isSkipRemovedRecords()) {
            throw new ImportServiceException('This preset is configured to skip added/removed records, so no local records must be removed.', 1550139159);
        }
        try {
            $result = $this->preset->removeAll();
        } catch (\Exception $exception) {
            throw new ImportServiceException(sprintf('Exception while removing all local record: %s', $exception->getMessage()), 1558014480, $exception);
        }
        return $result;
    }

    /** ----------------------- */

    /**
     * Dispatch the specified event to the configured handlers, if any.
     * @param string $eventName One of the EVENT_* constants
     * @param mixed ...$arguments Arguments to be passed to the event handler
     * @see on()
     *
     */
    private function dispatch(string $eventName, ...$arguments): void
    {
        if (!\array_key_exists($eventName, $this->callbacks)) {
            return;
        }
        /** @var \Closure $callback */
        foreach ($this->callbacks[$eventName] as $callback) {
            \call_user_func_array($callback, $arguments);
        }
    }

    /**
     * @param DataRecords $addedRecords
     * @throws ImportServiceException
     */
    private function addData(DataRecords $addedRecords): void
    {
        if ($addedRecords->isEmpty()) {
            return;
        }
        $this->dispatch(self::EVENT_PRE_ADD_DATA, $addedRecords);

        foreach ($addedRecords as $record) {
            $this->dispatch(self::EVENT_ADD_DATA, $record);
            try {
                $this->preset->addRecord($record);
            } catch (\Error $error) {
                $this->dispatch(self::EVENT_ERROR, sprintf('Error while adding record "%s": %s', $record->id()->value, $error->getMessage()));
                continue;
            } catch (\Exception $exception) {
                throw new ImportServiceException(sprintf('Exception while adding record %s: %s', $record->id()->value, $exception->getMessage()), 1558006707, $exception);
            }
        }

        $this->dispatch(self::EVENT_POST_ADD_DATA);
    }

    /**
     * @param DataRecords $updatedRecords
     * @param bool $forceUpdates
     * @throws ImportServiceException
     */
    private function updateData(DataRecords $updatedRecords, bool $forceUpdates): void
    {
        if ($updatedRecords->isEmpty()) {
            return;
        }
        $this->dispatch(self::EVENT_PRE_UPDATE_DATA, $updatedRecords, $forceUpdates);

        foreach ($updatedRecords as $record) {
            $this->dispatch(self::EVENT_UPDATE_DATA, $record);
            try {
                $this->preset->updateRecord($record);
            } catch (\Error $error) {
                $this->dispatch(self::EVENT_ERROR, sprintf('Error while updating record "%s": %s', $record->id()->value, $error->getMessage()));
                continue;
            } catch (\Exception $exception) {
                throw new ImportServiceException(sprintf('Exception while updating record %s: %s', $record->id()->value, $exception->getMessage()), 1558006801, $exception);
            }
        }

        $this->dispatch(self::EVENT_POST_UPDATE_DATA);
    }

    /**
     * Remove nodes corresponding to the specified data from the content repository
     *
     * @param DataIds $removedIds
     * @throws ImportServiceException
     */
    private function removeData(DataIds $removedIds): void
    {
        if ($removedIds->isEmpty()) {
            return;
        }

        $this->dispatch(self::EVENT_PRE_REMOVE_DATA, $removedIds);

        foreach ($removedIds as $dataId) {
            $this->dispatch(self::EVENT_REMOVE_DATA, $dataId);
            try {
                $this->preset->removeRecord($dataId);
            } catch (\Error $error) {
                $this->dispatch(self::EVENT_ERROR, sprintf('Error while removing record "%s": %s', $dataId->value, $error->getMessage()));
                continue;
            } catch (\Exception $exception) {
                throw new ImportServiceException(sprintf('Exception while removing record %s: %s', $dataId->value, $exception->getMessage()), 1558006816, $exception);
            }
        }

        $this->dispatch(self::EVENT_POST_REMOVE_DATA);
    }
}
