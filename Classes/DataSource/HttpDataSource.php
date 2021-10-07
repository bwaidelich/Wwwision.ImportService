<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataSource;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Uri;
use Neos\Error\Messages\Error;
use Neos\Error\Messages\Notice;
use Neos\Error\Messages\Result;
use Wwwision\ImportService\ImportServiceException;
use Wwwision\ImportService\OptionsSchema;
use Wwwision\ImportService\ValueObject\DataRecords;

/**
 * HTTP Data Source that allows to import records from some HTTP endpoint
 */
final class HttpDataSource implements DataSourceInterface
{

    /**
     * @var Client
     */
    private $httpClient;

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
        $defaultHttpOptions = [
            'headers' => [
                'Accept' => 'application/json'
            ]
        ];
        $this->httpClient = new Client($options['httpOptions'] ?? $defaultHttpOptions);
    }

    public static function getOptionsSchema(): OptionsSchema
    {
        return OptionsSchema::create()
            ->requires('endpoint', 'string')
            ->has('idAttributeName', 'string')
            ->has('versionAttributeName', 'string')
            ->has('httpOptions', 'array');
    }

    public static function createWithOptions(array $options): DataSourceInterface
    {
        return new static($options);
    }

    public function setup(): Result
    {
        $result = new Result();
        try {
            $this->httpClient->get($this->endpoint);
            $result->addNotice(new Notice('Endpoint "%s" can be accessed', null, [$this->endpoint]));
        } catch (GuzzleException $e) {
            $result->addError(new Error('Failed to access endpoint "%s": %s', $e->getCode(), [$this->endpoint, $e->getMessage()]));
        }
        return $result;
    }

    /**
     * @throws ImportServiceException
     */
    public function load(): DataRecords
    {
        try {
            $response = $this->httpClient->get($this->endpoint);
        } catch (GuzzleException $exception) {
            throw new ImportServiceException(sprintf('Request failed: %s.', $exception->getMessage()), 15102227415, $exception);
        }

        if ($response->getStatusCode() !== 200) {
            throw new ImportServiceException(sprintf('Unexpected response status code %s on endpoint %s.', $response->getStatusCode(), $this->endpoint), 15102213263);
        }
        try {
            $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ImportServiceException(sprintf('Unexpected response body or malformed JSON on endpoint %s.', $this->endpoint), 1633522969);
        }

        if (!\is_array($data)) {
            throw new ImportServiceException(sprintf('Unexpected response body or malformed JSON on endpoint %s.', $this->endpoint), 15203231319);
        }
        if (\count($data) === 0) {
            throw new ImportServiceException(sprintf('The Http endpoint %s returned an empty result', $this->endpoint), 15203231381);
        }
        return DataRecords::fromRawArray($data, $this->idAttributeName, $this->versionAttributeName);
    }
}
