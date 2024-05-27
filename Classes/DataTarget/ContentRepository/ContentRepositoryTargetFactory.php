<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataTarget\ContentRepository;

use Doctrine\ORM\EntityManagerInterface;
use Neos\ContentRepository\Domain\Model\NodeData;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Flow\Annotations as Flow;
use Neos\Fusion\Core\Cache\ContentCache;
use Wwwision\ImportService\DataTarget\DataTargetFactoryInterface;
use Wwwision\ImportService\DataTarget\Dbal\DbalTarget;
use Wwwision\ImportService\Mapper;
use Wwwision\ImportService\OptionsSchema;

/**
 * Factory for the {@see DbalTarget}
 *
 * Note: This Data Target requires the Neos.ContentRepository package to be installed
 */
#[Flow\Scope('singleton')]
final class ContentRepositoryTargetFactory implements DataTargetFactoryInterface
{

    public function __construct(
        private readonly NodeTypeManager $nodeTypeManager,
        private readonly ContentCache $contentCache,
        private readonly EntityManagerInterface $doctrineEntityManager,
        private readonly ContextFactoryInterface $contextFactory,
    ) {
    }

    public function create(Mapper $mapper, array $options): ContentRepositoryTarget
    {
        $this->requireContentRepositoryPackage();
        $parentNodeResolver = null;
        if (isset($options['parentNodeResolver'])) {
            [$className, $methodName] = explode('::', $options['parentNodeResolver'], 2);
            $parentNodeResolver = (new $className())->$methodName(...);
        }
        $nodeVariantsResolver = null;
        if (isset($options['nodeVariantsResolver'])) {
            [$className, $methodName] = explode('::', $options['nodeVariantsResolver'], 2);
            $nodeVariantsResolver = (new $className())->$methodName(...);
        }
        return new ContentRepositoryTarget(
            mapper: $mapper,
            nodeTypeManager: $this->nodeTypeManager,
            contentCache: $this->contentCache,
            doctrineEntityManager: $this->doctrineEntityManager,
            contextFactory: $this->contextFactory,
            nodeTypeName: $options['nodeType'],
            rootNodePath: isset($options['rootNodePath']) ? rtrim($options['rootNodePath'], '\/') : null,
            parentNodeResolver: $parentNodeResolver,
            rootNodeTypeName: $options['rootNodeType'] ?? null,
            idPrefix: $options['idPrefix'] ?? null,
            softDelete:$options['softDelete'] ?? false,
            nodeVariantsResolver: $nodeVariantsResolver,
        );
    }

    public function optionsSchema(): OptionsSchema
    {
        return OptionsSchema::create()
            ->requires('nodeType', 'string')
            ->has('rootNodePath', 'string')
            ->has('parentNodeResolver', 'callable')
            ->has('nodeVariantsResolver', 'callable')
            ->has('rootNodePath', 'string')
            ->has('rootNodeType', 'string')
            ->has('idPrefix', 'string')
            ->has('softDelete', 'boolean');
    }

    private function requireContentRepositoryPackage(): void
    {
        if (!class_exists(NodeData::class)) {
            throw new \RuntimeException('This data target requires the Neos.ContentRepository package to be installed!', 1558011349);
        }
    }
}
