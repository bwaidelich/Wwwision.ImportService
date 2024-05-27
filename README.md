# Wwwision.ImportService

Neos Flow package for importing data from different sources to configurable targets such as the Neos Content Repository or an arbitrary database table

## Usage

### Setup

Install this package using composer via

```bash
composer require wwwision/import-service
```

### Define an Import Preset

Add some Import Preset configuration to your projects `Settings.yaml`, for example:

```yaml
Wwwision:
  ImportService:
    presets:

      'some-prefix:some-name':
        source:
          factory: 'Wwwision\ImportService\DataSource\Http\HttpSourceFactory'
          options:
            endpoint: 'https://some-endpoint.tld/data.json'
        target:
          factory: 'Wwwision\ImportService\DataTarget\Dbal\DbalSourceFactory'
          options:
            table: 'some_table'
        mapping:
          'id': 'id'
          'given_name': 'firstName'
          'family_name': 'lastName'
```

### Run the import

```bash
./flow import:run some-prefix:some-name
```

## Pre-process data

Sometimes the data has to be processed before it is mapped to. This can be done with a `dataProcessor`.

### Example:

#### Implementation

A processor is any *public* method of any class that can be instantiated by Flow without additional arguments:

```php
<?php
declare(strict_types=1);
namespace Some\Package;

use Wwwision\ImportService\ValueObject\DataRecord;
use Wwwision\ImportService\ValueObject\DataRecords;

final class SomeProcessor
{

    public function someMethod(DataRecords $records): DataRecords
    {
        return $records->map(static fn (DataRecord $record) => $record->withAttribute('title', 'overridden'));
    }
}
```

*Note:* The processor class _can_ have dependencies, but it should be possible to create a new instance via `ObjectMananger::get($processorClassname)` without further arguments, i.e. the class should behave like a singleton (see https://flowframework.readthedocs.io/en/stable/TheDefinitiveGuide/PartIII/ObjectManagement.html)

#### Configuration

```yaml
Wwwision:
  ImportService:
    presets:
      '<preset-name>':
        # ...
        options:
          dataProcessor: 'Some\Package\SomeProcessor::someMethod'
```

*Note:* The syntax looks like the method has to be static, but that's not the case. It just has to satisfy PHPs `is_callable()` function

## Validate configuration

Configuration for this package is verbose and thus error-prone.
The settings can be validated against a schema via the following command:

```bash
./flow configuration:validate --type Settings --path Wwwision.ImportService
```

Which should produce the output

```bash
Validating Settings configuration on path Wwwision.ImportService

All Valid!
```

## CLI

This package provides the following CLI commands:

### `import:run`

Synchronizes data (add, update,delete) for the given preset.

#### Usage

```shell
./flow import:run [<options>] <preset>
```

#### Arguments

```
  --preset             The preset to use (see Wwwision.Import.presets setting)
```

#### Options

```
  --quiet              If set, no output, apart from errors, will be displayed
  --force-updates      If set, all local records will be updated regardless of their version/timestamp. This is useful for node type changes that require new data to be fetched
  --from-fixture       If set, the data will be loaded from a local fixture file instead of the configured data source
```

### `import:prune`

Removes all data for the given preset

#### Usage

```shell
./flow import:prune [<options>] <preset>
```

#### Arguments

```
  --preset             The preset to reset (see Wwwision.Import.presets setting)
```

#### Options

```
  --assume-yes         If set, "yes" will be assumed for the confirmation question
```

### `import:presets`

Lists all configured preset names

#### Usage

```shell
./flow import:presets
```

### `import:preset`

Displays configuration for a given preset

#### Usage

```shell
./flow import:preset <preset>
```

#### Arguments

```
  --preset             The preset to show configuration for (see Wwwision.Import.presets setting)
```

### `import:setup`

Set up the configured data source and target for the specified preset and/or display status

#### Usage

```shell
./flow import:setup <preset>
```

#### Arguments

```
  --preset             Name of the preset to set up
```

## Neos ContentRepository import

Importing data from a 3rd party API into the Neos Content Repository is one of the main requirements for this package.

To import records into the Content Repository, the privided `ContentRepositoryTarget` can be used, it has the following options:

```
nodeType (string, required): Type of the imported nodes
rootNodePath: (string, optional): Absolute path of the root node underneath imported nodes will placed
parentNodeResolver: (callable string, optional): Callback in the format ('<FQCN>::<methodName>') that is invoked to resolve the parent node for a given record. The signature is: \Closure(\Wwwision\ImportService\ValueObject\DataRecord): \Neos\ContentRepository\Domain\Model\Node
nodeVariantsResolver: (callable string, optional): Callback in the format ('<FQCN>::<methodName>') that is invoked to resolve dimension variants for a given record. The signature is: \Closure(\Wwwision\ImportService\ValueObject\DataRecord): array of dimension values
idPrefix: (string, optional): Optional prefix that is prepended to the dataRecord IDs in order to get globally unique node identifiers
softDelete (boolean, optional): If set, removed records lead to the corresponding node to be _disabled_ only. Otherwise they are deleted from the Content Repository
rootNodeType: (string, optional): Type of the root node, imported nodes are placed in – if set and no root node can be found, the importer tries to create it via ./flow importer:setup
```

**Note:** Either `rootNodePath` or `parentNodeResolver` have to be specified!

### Example configuration:

```yaml
Wwwision:
  ImportService:
    presets:

      'some:preset':
        source:
          # depending on the data source, see above
        target:
          factory: 'Wwwision\ImportService\DataTarget\ContentRepository\ContentRepositoryTargetFactory'
          options:
            nodeType: 'Some.Package:Type.Of.Imported.Nodes'
            rootNodePath: '/sites/some-site/some/path'
            # alternatively:
            # parentNodeResolver: Some`\Package\SomeSingleton::someMethod
            idPrefix: 'product-'
            softDelete: true
        mapping:
          'title': 'title' # just use the record title 1:1
          'price': '${record.priceNet + record.vat}' # arbitrary Eel expressions are supported
          'uriPathSegment': '${Some.Custom.Eelhelper(record.title + "-" + record.id)}' # ...including custom Eel helpers (registered via Neos.Fusion.defaultContext setting)
```

### Usage without Neos.ContentRepository package

If the Neos.ContentRepository package is not installed Flow's proxy class builder throws an `UnknownObjectException`.
Disable auto-wiring in `Objects.yaml`:

  ```yaml
Wwwision\ImportService\DataTarget\ContentRepositoryTarget:
  autowiring: false
```
