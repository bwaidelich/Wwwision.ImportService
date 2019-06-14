# Wwwision.ImportService

Neos Flow package for importing data from different sources to configurable targets such as the Neos Content Repository or an arbitrary database table

## Validate configuration

Configuration for this package is verbose and thus error prone.
The settings can be validated against a schema via the following command:

    ./flow configuration:validate --type Settings --path Wwwision.ImportService

Which should produce the output

    Validating Settings configuration on path Wwwision.ImportService
    
    All Valid!
