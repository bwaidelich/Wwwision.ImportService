<?php
declare(strict_types=1);
namespace Wwwision\ImportService\DataSource\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Uri;
use Neos\Error\Messages\Error;
use Neos\Error\Messages\Notice;
use Neos\Error\Messages\Result;
use Neos\Flow\Annotations as Flow;
use Wwwision\ImportService\DataSource\DataSourceInterface;
use Wwwision\ImportService\ImportServiceException;
use Wwwision\ImportService\ValueObject\DataRecords;

/**
 * HTTP Data Source that allows to import records from some HTTP endpoint
 */
#[Flow\Proxy(false)]
final class HttpSource implements DataSourceInterface
{

    private readonly Client $httpClient;

    public function __construct(
        private readonly Uri $endpoint,
        private readonly string $idAttributeName,
        private readonly string|null $versionAttributeName,
        array $httpOptions,
    )
    {
        $this->httpClient = new Client($httpOptions);
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
            throw new ImportServiceException(sprintf('Unexpected response body or malformed JSON on endpoint %s.', $this->endpoint), 1633522969, $e);
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
