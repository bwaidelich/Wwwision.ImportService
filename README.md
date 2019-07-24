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
