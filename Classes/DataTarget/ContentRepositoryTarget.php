<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataTarget;

use Wwwision\ImportService\EelEvaluator;
use Wwwision\ImportService\Mapper;
use Wwwision\ImportService\ValueObject\ChangeSet;
use Wwwision\ImportService\ValueObject\DataId;
use Wwwision\ImportService\ValueObject\DataIds;
use Wwwision\ImportService\ValueObject\DataRecordInterface;
use Wwwision\ImportService\ValueObject\DataRecords;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query;
use Neos\ContentRepository\Domain\Model\NodeData;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Domain\Utility\NodePaths;
use Neos\ContentRepository\Exception\NodeTypeNotFoundException;
use Neos\ContentRepository\Utility;
use Neos\Flow\Annotations as Flow;
use Neos\Fusion\Core\Cache\ContentCache;
use Neos\Utility\ObjectAccess;
use Wwwision\ImportService\ValueObject\DataVersion;

/**
 * Neos Content Repository data target
 */
final class ContentRepositoryTarget implements DataTargetInterface
{
    /**
     * @const int maximum number of items to add//update before flushing the Doctrine EntityManager
     */
    private const MAXIMUM_BATCH_SIZE = 1000;

    /**
     * @Flow\Inject
     * @var EntityManagerInterface
     */
    protected $doctrineEntityManager;

    /**
     * @var NodeTypeManager
     */
    private $nodeTypeManager;

    /**
     * @var ContentCache
     */
    private $contentCache;

    /**
     * @Flow\Inject
     * @var EelEvaluator
     */
    protected $eelRenderer;

    /**
     * @var Mapper
     */
    private $mapper;

    /**
     * @var string[]
     */
    private $nodeTypeName;

    /**
     * @var string
     */
    private $nodePath;

    /**
     * @var string|null
     */
    private $nodeName;

    /**
     * @var NodeType|null
     */
    private $cachedNodeType;

    /**
     * @var NodeData[]
     */
    private $cachedNodesByPath = [];

    /**
     * @var NodeData[]
     */
    private $cachedNodesById = [];

    /**
     * @var array
     */
    private $cacheTagsToFlush = [];

    /**
     * @var int
     */
    private $pendingTransactionCount = 0;

    protected function __construct(Mapper $mapper, array $options)
    {
        $this->requireContentRepositoryPackage();
        $this->mapper = $mapper;
        if (!isset($options['nodeType'])) {
            throw new \InvalidArgumentException('Missing option "nodeType"', 1558014570);
        }
        $this->nodeTypeName = $options['nodeType'];
        if (!isset($options['nodePath'])) {
            throw new \InvalidArgumentException('Missing option "nodePath"', 1558016570);
        }
        $this->nodePath = rtrim($options['nodePath'], '\/');
        $this->nodeName = $options['nodeName'] ?? null;
    }

    public function injectNodeTypeManager(NodeTypeManager $nodeTypeManager): void
    {
        $this->nodeTypeManager = $nodeTypeManager;
    }

    public function injectContentCache(ContentCache $contentCache): void
    {
        $this->contentCache = $contentCache;
    }

    public static function createWithMapperAndOptions(Mapper $mapper, array $options): DataTargetInterface
    {
        return new static($mapper, $options);
    }

    private function requireContentRepositoryPackage(): void
    {
        /** @noinspection ClassConstantCanBeUsedInspection */
        $className = 'Neos\\ContentRepository\\Domain\\Model\\NodeData';
        if (!class_exists($className)) {
            throw new \RuntimeException('This data target requires the Neos.ContentRepository package to be installed!', 1558011349);
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
        $nodeDataRecords = $query->execute([], Query::HYDRATE_ARRAY);

        // this filters hidden nodes. Otherwise they would end up in $dataToRemove every time
        $activeNodeDataIdentifiers = [];
        $allNodeDataIdentifiers = [];
        foreach ($nodeDataRecords as $nodeData) {
            if ((int)$nodeData['hidden'] !== 1) {
                $activeNodeDataIdentifiers[] = (string)$nodeData['identifier'];
            }
            $allNodeDataIdentifiers[] = (string)$nodeData['identifier'];
        }
        $activeNodeDataIds = DataIds::fromStringArray($activeNodeDataIdentifiers);
        $allNodeDataIds = DataIds::fromStringArray($allNodeDataIdentifiers);
        $removedIds = $skipRemovedRecords ? DataIds::createEmpty() : $activeNodeDataIds->diff($records->getIds());
        $localDataLastModificationDates = array_column($nodeDataRecords, 'lastPublicationDateTime', 'identifier');

        $isUpdatedClosure = static function(DataRecordInterface $record) use ($localDataLastModificationDates) {
            if ($record->version()->isNotSet()) {
                return true;
            }
            if (!\array_key_exists($record->id()->toString(), $localDataLastModificationDates)) {
                return true;
            }
            $localVersion = DataVersion::fromDateTime($localDataLastModificationDates[$record->id()->toString()]);
            return $record->version()->isHigherThan($localVersion);
        };

        $updatedRecords = DataRecords::createEmpty();
        $addedRecords = DataRecords::createEmpty();
        foreach ($records as $record) {
            if (!$allNodeDataIds->has($record->id())) {
                if (!$skipAddedRecords) {
                    $addedRecords = $addedRecords->withRecord($record);
                }
                continue;
            }
            if ($forceUpdates || $isUpdatedClosure($record)) {
                $updatedRecords = $updatedRecords->withRecord($record);
            }
        }
        return ChangeSet::fromAddedUpdatedAndRemoved($addedRecords, $updatedRecords, $removedIds);
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
        /** @var NodeType $nodeType */
        foreach ($this->nodeTypeManager->getSubNodeTypes($this->nodeTypeName) as $nodeType) {
            $nodeTypeNames[] = $nodeType->getName();
        }
        return $nodeTypeNames;
    }

    public function addRecord(DataRecordInterface $record): void
    {
        $nodePath = $this->eelRenderer->evaluateIfExpression($this->nodePath, ['record' => $record]);
        $parentNodeData = $this->getNodeDataByPath((string)$nodePath);

        if ($this->nodeName !== null) {
            $nodeName = $this->eelRenderer->evaluateIfExpression($this->nodeName, ['record' => $record]);
        } else {
            $nodeName = NodePaths::generateRandomNodeName();
        }
        $nodeData = $this->createNodeData($parentNodeData, $nodeName, $this->nodeType(), $record->id()->toString());
        $this->mapNodeData($nodeData, $record);
        $this->registerNodeDataChange($nodeData);
        if (++$this->pendingTransactionCount % self::MAXIMUM_BATCH_SIZE === 0) {
            $this->doctrineEntityManager->flush();
        }
    }

    public function updateRecord(DataRecordInterface $record): void
    {
        $nodeData = $this->getNodeDataByDataId($record->id());
        $this->mapNodeData($nodeData, $record);
        $nodeData->setHidden(false);
        $this->doctrineEntityManager->persist($nodeData);
        $this->registerNodeDataChange($nodeData);
        if (++$this->pendingTransactionCount % self::MAXIMUM_BATCH_SIZE === 0) {
            $this->doctrineEntityManager->flush();
        }
    }

    public function removeRecord(DataId $dataId): void
    {
        $nodeData = $this->getNodeDataByDataId($dataId);
        $nodeData->setHidden(true);
        $this->registerNodeDataChange($nodeData);
        if (++$this->pendingTransactionCount % self::MAXIMUM_BATCH_SIZE === 0) {
            $this->doctrineEntityManager->flush();
        }
    }

    public function removeAll(): int
    {
        $query = $this->doctrineEntityManager->createQuery('DELETE FROM ' . NodeData::class . ' n WHERE n.path LIKE :pathPrefix AND n.nodeType IN (:nodeTypeNames)');
        $query->setParameters([
            'pathPrefix' => $this->nodePath . '/%',
            'nodeTypeNames' => $this->affectedNodeTypeNames(),
        ]);
        $result = $query->execute();
        if ($result === null) {
            throw new \RuntimeException('Failed to remove affected nodes', 1558356631);
        }
        $this->doctrineEntityManager->flush();
        return (int)$result;
    }

    public function finalize(): void
    {
        $this->doctrineEntityManager->flush();
        $this->flushCaches();
        $this->pendingTransactionCount = 0;
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
        $this->cacheTagsToFlush['NodeType_' . $workspaceHash . '_' . $nodeType->getName()] = true;

        $ascendantNode = $nodeData;
        while ($ascendantNode !== null && $ascendantNode->getDepth() > 1) {
            $this->cacheTagsToFlush['DescendantOf_' . $workspaceHash . '_' . $ascendantNode->getIdentifier()] = true;
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
        $mappedValues = array_filter($this->mapper->mapRecord($record), static function($value) {
            return $value !== null;
        });
        foreach ($mappedValues as $propertyName => $propertyValue) {
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

    private function getNodeDataByDataId(DataId $dataId): NodeData
    {
        if (!\array_key_exists($dataId->toString(), $this->cachedNodesById)) {
            try {
                $query = $this->doctrineEntityManager->createQuery('SELECT n FROM ' . NodeData::class . ' n WHERE n.identifier = :identifier AND n.workspace = :liveWorkspaceName');
                $query->setParameters([
                    'identifier' => $dataId,
                    'liveWorkspaceName' => 'live',
                ]);
                $this->cachedNodesById[$dataId->toString()] = $query->getOneOrNullResult();
            } catch (NonUniqueResultException $exception) {
                throw new \RuntimeException(sprintf('Selecting node %s returned a non unique result.', $dataId), 1558078426, $exception);
            }
            if ($this->cachedNodesById[$dataId->toString()] === null) {
                throw new \RuntimeException(sprintf('Could not find node %s.', $dataId), 1529323300);
            }
        }
        return $this->cachedNodesById[$dataId->toString()];
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
     * @param int $index
     * @return NodeData
     */
    private function createNodeData(NodeData $parentNodeData, string $name, NodeType $nodeType, string $identifier, int $index = null): NodeData
    {
        $newNodePath = NodePaths::addNodePathSegment($parentNodeData->getPath(), $name);
        $newNodeData = new NodeData($newNodePath, $parentNodeData->getWorkspace(), $identifier, $parentNodeData->getDimensionValues());
        $newNodeData->setNodeType($nodeType);
        if ($index !== null) {
            $newNodeData->setIndex($index);
        }

        foreach ($nodeType->getDefaultValuesForProperties() as $propertyName => $propertyValue) {
            if (strncmp($propertyName, '_', 1) === 0) {
                ObjectAccess::setProperty($newNodeData, substr($propertyName, 1), $propertyValue);
            } else {
                $newNodeData->setProperty($propertyName, $propertyValue);
            }
        }

        $query = $this->doctrineEntityManager->createQuery('SELECT n.identifier FROM ' . NodeData::class . ' n WHERE n.parentPath = :nodePath AND n.workspace = :liveWorkspaceName');
        $existingChildNodeIdentifiers = array_column($query->execute(['nodePath' => $newNodePath, 'liveWorkspaceName' => 'live'], Query::HYDRATE_ARRAY), 'identifier');
        foreach ($nodeType->getAutoCreatedChildNodes() as $childNodeName => $childNodeType) {
            $childNodeIdentifier = Utility::buildAutoCreatedChildNodeIdentifier($childNodeName, $newNodeData->getIdentifier());
            if (\in_array($childNodeIdentifier, $existingChildNodeIdentifiers, true)) {
                continue;
            }
            // Recursively create child node data for auto created child nodes.
            // This is very important because of the nested structure of many node types.
            $this->createNodeData($newNodeData, $childNodeName, $childNodeType, $childNodeIdentifier);
        }

        $this->doctrineEntityManager->persist($newNodeData);
        $this->registerNodeDataChange($newNodeData);
        return $newNodeData;
    }
}
