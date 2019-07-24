<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataSource;

use Wwwision\ImportService\ImportServiceException;
use Wwwision\ImportService\OptionsSchema;
use Wwwision\ImportService\ValueObject\DataRecords;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Client\Browser;
use Neos\Flow\Http\Client\CurlEngineException;
use Neos\Flow\Http\Request;
use Neos\Flow\Http\Uri;

/**
 * HTTP Data Source that allows to import records from some HTTP endpoint
 */
final class HttpDataSource implements DataSourceInterface
{

    /**
     * @Flow\Inject
     * @var Browser
     */
    protected $httpClient;

    /**
     * @var Uri
     */
    private $endpoint;

    /**
     * @var string
     */
    private $idAttributeName;

    /**
     * @var string|null
     */
    private $versionAttributeName;

    protected function __construct(array $options)
    {
        $this->endpoint = new Uri($options['endpoint']);
        $this->idAttributeName = $options['idAttributeName'] ?? 'id';
        $this->versionAttributeName = $options['versionAttributeName'] ?? null;
    }

    public static function getOptionsSchema(): OptionsSchema
    {
        return OptionsSchema::create()
            ->requires('endpoint', 'string')
            ->has('idAttributeName', 'string')
            ->has('versionAttributeName', 'string');
    }

    public static function createWithOptions(array $options): DataSourceInterface
    {
        return new static($options);
    }

    /**
     * @throws ImportServiceException
     */
    public function load(): DataRecords
    {
        try {
            $request = Request::create($this->endpoint);
            /** @noinspection PhpDeprecationInspection */
            $request->setHeader('Accept', 'application/json');
            $response = $this->httpClient->sendRequest($request);
        } /** @noinspection PhpRedundantCatchClauseInspection */ catch (CurlEngineException $exception) {
            throw new ImportServiceException(sprintf('Request failed: %s.', $exception->getMessage()), 15102227415, $exception);
        }

        if ($response->getStatusCode() !== 200) {
            throw new ImportServiceException(sprintf('Unexpected response status code %s on endpoint %s.', $response->getStatusCode(), $this->endpoint), 15102213263);
        }
        $data = json_decode($response->getBody()->getContents(), true);

        if (!\is_array($data)) {
            throw new ImportServiceException(sprintf('Unexpected response body or malformed JSON on endpoint %s.', $this->endpoint), 15203231319);
        }
        if (\count($data) === 0) {
            throw new ImportServiceException(sprintf('The Http endpoint %s returned an empty result', $this->endpoint), 15203231381);
        }
        return DataRecords::fromRawArray($data, $this->idAttributeName, $this->versionAttributeName);
    }
}
