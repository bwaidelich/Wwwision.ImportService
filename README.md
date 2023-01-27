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
          className: 'Wwwision\ImportService\DataSource\HttpDataSource'
          options:
            endpoint: 'https://some-endpoint.tld/data.json'
        target:
          className: 'Wwwision\ImportService\DataTarget\DbalTarget'
          options:
            table: 'some_table'
        mapping:
          'id': 'id'
          'given_name': 'firstName'
          'family_name': 'lastName'
```

### Run the import

```bash
./flow import:import some-prefix:some-name
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

Configuration for this package is verbose and thus error prone.
The settings can be validated against a schema via the following command:

```bash
./flow configuration:validate --type Settings --path Wwwision.ImportService
```

Which should produce the output

```bash
Validating Settings configuration on path Wwwision.ImportService

All Valid!
```

### Usage without Neos.ContentRepository package

If the Neos.ContentRepository package is not installed Flow's proxy class builder throws an `UnknownObjectException`.
Disable autowiring in `Objects.yaml`:

```yaml
Wwwision\ImportService\DataTarget\ContentRepositoryTarget:
  autowiring: false
```
