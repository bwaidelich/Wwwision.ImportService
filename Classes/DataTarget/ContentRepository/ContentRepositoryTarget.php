<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataTarget\ContentRepository;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Neos\ContentRepository\Domain\Model\NodeData;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\NodeTemplate;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Domain\Utility\NodePaths;
use Neos\ContentRepository\Exception\NodeTypeNotFoundException;
use Neos\ContentRepository\Utility;
use Neos\Error\Messages\Error;
use Neos\Error\Messages\Notice;
use Neos\Error\Messages\Result;
use Neos\Error\Messages\Warning;
use Neos\Flow\Annotations as Flow;
use Neos\Fusion\Core\Cache\ContentCache;
use Neos\Utility\ObjectAccess;
use Neos\Utility\TypeHandling;
use Wwwision\ImportService\DataTarget\DataTargetInterface;
use Wwwision\ImportService\Mapper;
use Wwwision\ImportService\ValueObject\ChangeSet;
use Wwwision\ImportService\ValueObject\DataId;
use Wwwision\ImportService\ValueObject\DataIds;
use Wwwision\ImportService\ValueObject\DataRecordInterface;
use Wwwision\ImportService\ValueObject\DataRecords;
use Wwwision\ImportService\ValueObject\DataVersion;

/**
 * Neos Content Repository Data Target that allows to import records as nodes into the Neos ContentRepository
 *
 * Note: This Data Target requires the Neos.ContentRepository package to be installed
 */
#[Flow\Proxy(false)]
final class ContentRepositoryTarget implements DataTargetInterface
{
    /**
     * @const int maximum number of items to add//update before flushing the Doctrine EntityManager
     */
    private const MAXIMUM_BATCH_SIZE = 1000;

    private NodeType|null $cachedNodeType = null;

    /**
     * @var array<string, NodeData>
     */
    private array $cachedNodesByPath = [];

    /**
     * @var array<string, array<NodeData>>
     */
    private array $cachedNodesById = [];

    /**
     * @var array<string, true>
     */
    private array $cacheTagsToFlush = [];

    private int $pendingTransactionCount = 0;

    public function __construct(
        private readonly Mapper $mapper,
        private readonly NodeTypeManager $nodeTypeManager,
        private readonly ContentCache $contentCache,
        private readonly EntityManagerInterface $doctrineEntityManager,
        private readonly ContextFactoryInterface $contextFactory,
        private readonly string $nodeTypeName,
        private readonly string|null $rootNodePath,
        private readonly \Closure|null $parentNodeResolver,
        private readonly string|null $rootNodeTypeName,
        private readonly string|null $idPrefix,
        private readonly bool $softDelete,
        private readonly \Closure|null $nodeVariantsResolver,
    ) {
        if ($rootNodePath === null && $parentNodeResolver === null) {
            throw new \InvalidArgumentException('Missing option "rootNodePath" and/or "parentNodeDataResolver"', 1558016570);
        }
    }

    public function computeDataChanges(DataRecords $records, bool $forceUpdates, bool $skipAddedRecords, bool $skipRemovedRecords): ChangeSet
    {
        // We use the "lastPublicationDateTime" field of NodeData because "lastModificationDateTime" is updated by a Doctrine hook and can't be set manually:
        $query = $this->doctrineEntityManager->createQuery('SELECT n.identifier, n.lastPublicationDateTime, n.hidden FROM ' . NodeData::class . ' n WHERE n.nodeType IN (:nodeTypeNames)');
        $query->setParameters([
            'nodeTypeNames' => $this->affectedNodeTypeNames(),
        ]);
        /** @var array $nodeDataRecords */
        $nodeDataRecords = $query->execute([], AbstractQuery::HYDRATE_ARRAY);

        // this filters hidden nodes. Otherwise, they would end up in $dataToRemove every time
        $allIds = [];
        $activeIds = [];
        foreach ($nodeDataRecords as $nodeData) {
            $recordIdentifier = $this->idPrefix !== null ? substr($nodeData['identifier'], \strlen($this->idPrefix)) : $nodeData['identifier'];
            if ((int)$nodeData['hidden'] !== 1) {
                $activeIds[] = $recordIdentifier;
            }
            $allIds[] = $recordIdentifier;
        }
        $allDataIds = DataIds::fromStringArray($allIds);
        $activeDataIds = DataIds::fromStringArray($activeIds);
        $removedDataIds = $skipRemovedRecords ? DataIds::createEmpty() : $activeDataIds->diff($records->getIds());
        $localDataLastModificationDates = array_column($nodeDataRecords, 'lastPublicationDateTime', 'identifier');

        $isUpdatedClosure = static function (DataRecordInterface $record) use ($localDataLastModificationDates) {
            if ($record->version()->isNotSet()) {
                return true;
            }
            if (!\array_key_exists($record->id()->value, $localDataLastModificationDates)) {
                return true;
            }
            $localVersion = DataVersion::fromDateTime($localDataLastModificationDates[$record->id()->value]);
            return $record->version()->isHigherThan($localVersion);
        };

        $updatedRecords = DataRecords::createEmpty();
        $addedRecords = DataRecords::createEmpty();
        foreach ($records as $record) {
            if (!$allDataIds->has($record->id())) {
                if (!$skipAddedRecords) {
                    $addedRecords = $addedRecords->withRecord($record);
                }
                continue;
            }
            if ($forceUpdates || $isUpdatedClosure($record)) {
                $updatedRecords = $updatedRecords->withRecord($record);
            }
        }
        return ChangeSet::fromAddedUpdatedAndRemoved($addedRecords, $updatedRecords, $removedDataIds);
    }

    private function nodeType(): NodeType
    {
        if ($this->cachedNodeType === null) {
            try {
                $this->cachedNodeType = $this->nodeTypeManager->getNodeType($this->nodeTypeName);
            } catch (NodeTypeNotFoundException $exception) {
                throw new \RuntimeException(sprintf('Invalid node type "%s"', $this->nodeTypeName), 1558356447, $exception);
            }
        }
        return $this->cachedNodeType;
    }

    /**
     * @return string[]
     */
    private function affectedNodeTypeNames(): array
    {
        $nodeTypeNames = [$this->nodeTypeName];
        foreach ($this->nodeTypeManager->getSubNodeTypes($this->nodeTypeName) as $nodeType) {
            $nodeTypeNames[] = $nodeType->getName();
        }
        return $nodeTypeNames;
    }

    public function addRecord(DataRecordInterface $record): void
    {
        if ($this->parentNodeResolver !== null) {
            $parentNode = \call_user_func($this->parentNodeResolver, $record);
            if (!$parentNode instanceof NodeInterface) {
                throw new \RuntimeException(sprintf('The "parentNodeResolver" must return an instance of %s, got: %s for record "%s"', NodeInterface::class, TypeHandling::getTypeForValue($parentNode), $record->id()->value), 1563977620);
            }
            $parentNodeData = $parentNode->getNodeData();
        } else {
            $parentNodeData = $this->getNodeDataByPath($this->rootNodePath);
        }

        if ($this->nodeVariantsResolver !== null) {
            $nodeVariants = \call_user_func($this->nodeVariantsResolver, $record);
            if (!\is_array($nodeVariants)) {
                throw new \RuntimeException(sprintf('The "nodeVariantsResolver" must return an array, got: %s for record "%s"', TypeHandling::getTypeForValue($nodeVariants), $record->id()->value), 1563977893);
            }
            if ($nodeVariants === []) {
                throw new \RuntimeException(sprintf('The "nodeVariantsResolver" returned an empty array for record "%s"', $record->id()->value), 1563977956);
            }
        } else {
            $nodeVariants = [$parentNodeData->getDimensionValues()];
        }
        foreach ($nodeVariants as $variantDimensionValues) {
            $nodeIdentifier = $this->nodeIdentifier($record->id());
            $nodeData = $this->createNodeData($parentNodeData, NodePaths::generateRandomNodeName(), $this->nodeType(), $nodeIdentifier, $variantDimensionValues);
            $this->mapNodeData($nodeData, $record);
            $this->registerNodeDataChange($nodeData);
            if (++$this->pendingTransactionCount % self::MAXIMUM_BATCH_SIZE === 0) {
                $this->doctrineEntityManager->flush();
            }
        }
    }

    public function updateRecord(DataRecordInterface $record): void
    {
        foreach ($this->getNodeDatasByDataId($record->id()) as $nodeData) {
            $this->mapNodeData($nodeData, $record);
            $nodeData->setHidden(false);
            $this->doctrineEntityManager->persist($nodeData);
            $this->registerNodeDataChange($nodeData);
            if (++$this->pendingTransactionCount % self::MAXIMUM_BATCH_SIZE === 0) {
                $this->doctrineEntityManager->flush();
            }
        }
    }

    public function removeRecord(DataId $dataId): void
    {
        foreach ($this->getNodeDatasByDataId($dataId) as $nodeData) {
            $this->removeNodeData($nodeData);
        }
    }

    public function removeAll(): int
    {
        if ($this->rootNodePath !== null) {
            $query = $this->doctrineEntityManager->createQuery('SELECT n FROM ' . NodeData::class . ' n WHERE n.path LIKE :pathPrefix AND n.nodeType IN (:nodeTypeNames)')->setParameters([
                'pathPrefix' => $this->rootNodePath . '/%',
                'nodeTypeNames' => $this->affectedNodeTypeNames(),
            ]);
        } else {
            $query = $this->doctrineEntityManager->createQuery('SELECT n FROM ' . NodeData::class . ' n WHERE n.nodeType IN (:nodeTypeNames)')->setParameters([
                'nodeTypeNames' => $this->affectedNodeTypeNames(),
            ]);
        }
        $numberOfRemovedNodes = 0;
        /** @var NodeData[] $nodeDatasToRemove */
        $nodeDatasToRemove = $query->getResult();
        foreach ($nodeDatasToRemove as $nodeData) {
            $this->removeNodeData($nodeData);
            $numberOfRemovedNodes ++;
        }
        $this->finalize();
        return $numberOfRemovedNodes;
    }

    public function finalize(): void
    {
        $this->doctrineEntityManager->flush();
        $this->flushCaches();
        $this->pendingTransactionCount = 0;
    }

    private function removeNodeData(NodeData $nodeData): void
    {
        if ($this->softDelete) {
            $nodeData->setHidden(true);
        } else {
            $nodeData->remove();
        }
        $this->registerNodeDataChange($nodeData);
        if (++$this->pendingTransactionCount % self::MAXIMUM_BATCH_SIZE === 0) {
            $this->doctrineEntityManager->flush();
        }
    }

    private function registerNodeDataChange(NodeData $nodeData): void
    {
        try {
            $nodeType = $nodeData->getNodeType();
        } catch (NodeTypeNotFoundException $exception) {
            throw new \RuntimeException(sprintf('Could not resolve node type of node %s', $nodeData->getIdentifier()), 1558356769, $exception);
        }
        $workspaceHash = '%d0dbe915091d400bd8ee7f27f0791303%'; // === CachingHelper::renderWorkspaceTagForContextNode('live');
        $this->cacheTagsToFlush['Node_' . $workspaceHash . '_' . $nodeData->getIdentifier()] = true;
        $this->cacheTagsToFlush['Node_'  . $nodeData->getIdentifier()] = true;
        $this->cacheTagsToFlush['NodeType_' . $workspaceHash . '_' . $nodeType->getName()] = true;
        $this->cacheTagsToFlush['NodeType_' . $nodeType->getName()] = true;

        $ascendantNode = $nodeData;
        while ($ascendantNode !== null && $ascendantNode->getDepth() > 1) {
            $this->cacheTagsToFlush['DescendantOf_' . $workspaceHash . '_' . $ascendantNode->getIdentifier()] = true;
            $this->cacheTagsToFlush['DescendantOf_' . $ascendantNode->getIdentifier()] = true;
            $ascendantNode = $ascendantNode->getParent();
        }
    }

    /**
     * Flushes Content Caches for all modified NodeData instances (tracked via self::modifiedNodeDataInstances)
     */
    private function flushCaches(): void
    {
        if ($this->cacheTagsToFlush === []) {
            return;
        }
        $this->contentCache->flushByTag(ContentCache::TAG_EVERYTHING);
        foreach (array_keys($this->cacheTagsToFlush) as $cacheTag) {
            $this->contentCache->flushByTag($cacheTag);
        }
        $this->cacheTagsToFlush = [];
    }

    private function mapNodeData(NodeData $nodeData, DataRecordInterface $record): void
    {
        foreach ($this->mapper->mapRecord($record, ['nodeData' => $nodeData]) as $propertyName => $propertyValue) {
            $nodeData->setProperty($propertyName, $propertyValue);
        }
        if ($record->hasAttribute('_timestamp')) {
            $nodeData->setLastPublicationDateTime($record->attribute('_timestamp'));
        }
    }

    private function getNodeDataByPath(string $nodePath): NodeData
    {
        if (!\array_key_exists($nodePath, $this->cachedNodesByPath)) {
            try {
                $query = $this->doctrineEntityManager->createQuery('SELECT n FROM ' . NodeData::class . ' n WHERE n.path = :path AND n.workspace = :liveWorkspaceName');
                $query->setParameters([
                    'path' => $nodePath,
                    'liveWorkspaceName' => 'live',
                ]);
                $this->cachedNodesByPath[$nodePath] = $query->getOneOrNullResult();
            } catch (NonUniqueResultException $exception) {
                throw new \RuntimeException(sprintf('Selecting node on path "%s" returned a non unique result.', $nodePath), 1558447152, $exception);
            }
            if ($this->cachedNodesByPath[$nodePath] === null) {
                throw new \RuntimeException(sprintf('Could not find node on path "%s".', $nodePath), 1558447168);
            }
        }
        return $this->cachedNodesByPath[$nodePath];
    }

    /**
     * @param DataId $dataId
     * @return NodeData[]
     */
    private function getNodeDatasByDataId(DataId $dataId): array
    {
        $nodeIdentifier = $this->nodeIdentifier($dataId);
        if (\array_key_exists($nodeIdentifier, $this->cachedNodesById)) {
            return $this->cachedNodesById[$nodeIdentifier];
        }
        $query = $this->doctrineEntityManager->createQuery('SELECT n FROM ' . NodeData::class . ' n WHERE n.identifier = :identifier AND n.workspace = :liveWorkspaceName');
        $query->setParameters([
            'identifier' => $nodeIdentifier,
            'liveWorkspaceName' => 'live',
        ]);
        $this->cachedNodesById[$nodeIdentifier] = $query->getResult();
        if ($this->cachedNodesById[$nodeIdentifier] === []) {
            throw new \RuntimeException(sprintf('Could not find node "%s".', $nodeIdentifier), 1529323300);
        }
        return $this->cachedNodesById[$nodeIdentifier];
    }

    private function nodeIdentifier(DataId $dataId): string
    {
        return $this->idPrefix !== null ? $this->idPrefix . $dataId->value : $dataId->value;
    }

    /**
     * Create a new node data without expensive checks
     *
     * This replicates behavior from NodeData and Node and does not emit signals and perform expensive checks.
     *
     * @param NodeData $parentNodeData
     * @param string $name
     * @param NodeType $nodeType
     * @param string $identifier
     * @param array $dimensionValues
     * @return NodeData
     */
    private function createNodeData(NodeData $parentNodeData, string $name, NodeType $nodeType, string $identifier, array $dimensionValues): NodeData
    {
        $newNodePath = NodePaths::addNodePathSegment($parentNodeData->getPath(), $name);
        $newNodeData = new NodeData($newNodePath, $parentNodeData->getWorkspace(), $identifier, $dimensionValues);
        $newNodeData->setNodeType($nodeType);

        foreach ($nodeType->getDefaultValuesForProperties() as $propertyName => $propertyValue) {
            if (str_starts_with($propertyName, '_')) {
                ObjectAccess::setProperty($newNodeData, substr($propertyName, 1), $propertyValue);
            } else {
                $newNodeData->setProperty($propertyName, $propertyValue);
            }
        }

        $query = $this->doctrineEntityManager->createQuery('SELECT n.identifier FROM ' . NodeData::class . ' n WHERE n.parentPath = :nodePath AND n.workspace = :liveWorkspaceName');
        $existingChildNodeIdentifiers = array_column($query->execute(['nodePath' => $newNodePath, 'liveWorkspaceName' => 'live'], AbstractQuery::HYDRATE_ARRAY), 'identifier');
        foreach ($nodeType->getAutoCreatedChildNodes() as $childNodeName => $childNodeType) {
            $childNodeIdentifier = Utility::buildAutoCreatedChildNodeIdentifier($childNodeName, $newNodeData->getIdentifier());
            if (\in_array($childNodeIdentifier, $existingChildNodeIdentifiers, true)) {
                continue;
            }
            // Recursively create child node data for auto created child nodes.
            // This is very important because of the nested structure of many node types.
            $this->createNodeData($newNodeData, $childNodeName, $childNodeType, $childNodeIdentifier, $dimensionValues);
        }

        $this->doctrineEntityManager->persist($newNodeData);
        $this->registerNodeDataChange($newNodeData);
        return $newNodeData;
    }

    public function setup(): Result
    {
        $result = new Result();
        if ($this->parentNodeResolver !== null) {
            return $result;
        }
        if ($this->rootNodePath !== null) {
            try {
                $rootNodeData = $this->getNodeDataByPath($this->rootNodePath);
                $result->addNotice(new Notice('Root node of type "%s" exists at "%s"', null, [$rootNodeData->getNodeType()->getName(), $this->rootNodePath]));
            } catch (\RuntimeException $exception) {
                if ($this->rootNodeTypeName === null) {
                    $result->addError(new Error('%s. No "rootNodeType" is configured', $exception->getCode(), [$exception->getMessage()]));
                } else if (substr_count($this->rootNodePath, '/') !== 1) {
                    $result->addError(new Error('Configured "rootNodePath" has to be on the root level ("/some-name") in order to be auto-generated. Given: "%s"', null, [$this->rootNodePath]));
                } else {
                    $result->addWarning(new Warning('%s. Trying to create root node of type "%s"', $exception->getCode(), [$exception->getMessage(), $this->rootNodeTypeName]));
                    $context = $this->contextFactory->create();
                    $rootNodeTemplate = new NodeTemplate();
                    $rootNodeTemplate->setNodeType($this->nodeTypeManager->getNodeType($this->rootNodeTypeName));
                    $rootNodeTemplate->setName(ltrim($this->rootNodePath, '/'));
                    $context->getRootNode()->createNodeFromTemplate($rootNodeTemplate);
                    $result->addNotice(new Notice('Created root node of type "%s" at "%s"', null, [$this->rootNodeTypeName, $this->rootNodePath]));
                }
            }
        } else {
            $result->addWarning(new Warning('No "rootNodePath" or "parentNodeResolver" configured'));
        }
        return $result;
    }
}
