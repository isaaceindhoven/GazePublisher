<?php

declare(strict_types=1);

namespace ISAAC\GazePublisher;

use Firebase\JWT\JWT;
use ISAAC\GazePublisher\ErrorHandlers\ErrorHandler;
use ISAAC\GazePublisher\ErrorHandlers\RethrowingErrorHandler;
use ISAAC\GazePublisher\Exceptions\HubEmitRejectedException;
use ISAAC\GazePublisher\Exceptions\InvalidGazeHubUrlException;
use ISAAC\GazePublisher\Exceptions\InvalidPayloadException;
use ISAAC\GazePublisher\HttpClient\CurlClient;
use ISAAC\GazePublisher\HttpClient\HttpClient;
use JsonException;

use function filter_var;
use function json_encode;
use function rand;
use function rtrim;
use function time;
use function uniqid;

use const FILTER_VALIDATE_URL;
use const JSON_THROW_ON_ERROR;

class GazePublisher
{
    /**
     * @var string
     */
    private $privateKeyContent;

    /**
     * @var string
     */
    private $hubUrl;

    /**
     * @var int
     */
    private $maxRetries;

    /**
     * @var ErrorHandler
     */
    private $errorHandler;

    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * @param string $hubUrl
     * @param string $privateKeyContent
     * @param int $maxRetries
     * @param ErrorHandler $errorHandler
     * @param HttpClient $httpClient
     * @throws InvalidGazeHubUrlException
     */
    public function __construct(
        string $hubUrl,
        string $privateKeyContent,
        int $maxRetries = 3,
        ErrorHandler $errorHandler = null,
        HttpClient $httpClient = null
    ) {
        if ($errorHandler === null) {
            $errorHandler = new RethrowingErrorHandler();
        }

        if ($httpClient === null) {
            $httpClient = new CurlClient();
        }

        $this->errorHandler = $errorHandler;
        $this->httpClient = $httpClient;
        $this->setHubUrl($hubUrl);
        $this->privateKeyContent = $privateKeyContent;
        $this->maxRetries = $maxRetries;
    }

    /**
     * @param string $topic
     * @param null|mixed $payload
     * @param string|null $role
     * @throws HubEmitRejectedException
     */
    public function emit(string $topic, $payload = null, string $role = null): void
    {
        $httpCode = $this->sendToHub($topic, $payload, $role);
        $tries = 1;

        while ($httpCode !== 200 && ++$tries <= $this->maxRetries) {
            $httpCode = $this->sendToHub($topic, $payload, $role);
        }

        if ($httpCode !== 200) {
            $this->errorHandler->handleException(new HubEmitRejectedException());
        }
    }

    /**
     * @param string[] $clientRoles
     * @param int $expirationInMinutes
     * @return string
     */
    public function generateClientToken(array $clientRoles = [], int $expirationInMinutes = 300): string
    {
        return $this->generateJwt(['roles' => $clientRoles], $expirationInMinutes);
    }

    /**
     * @param string $topic
     * @param mixed $payload
     * @param string|null $role
     * @return int
     * @throws InvalidPayloadException
     */
    private function sendToHub(string $topic, $payload, string $role = null): int
    {
        $jwt = $this->generateJwt(['role' => 'server'], 1);

        try {
            return $this->httpClient->post(
                $this->hubUrl . '/event',
                json_encode(['payload' => $payload, 'topic' => $topic, 'role' => $role ], JSON_THROW_ON_ERROR),
                ['Content-Type: application/json', 'Authorization: Bearer ' . $jwt ]
            );
        } catch (JsonException $e) {
            $this->errorHandler->handleException(new InvalidPayloadException());
            return 500;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param int $expirationInMinutes
     * @return string
     */
    private function generateJwt(array $data, int $expirationInMinutes): string
    {
        $payload =  $data + [
            'exp' => time() + 60 * $expirationInMinutes,
            'jti' => uniqid((string) rand(), true),
        ];

        return JWT::encode($payload, $this->privateKeyContent, 'RS256');
    }

    /**
     * @param string $hubUrl
     * @throws InvalidGazeHubUrlException
     */
    private function setHubUrl(string $hubUrl): void
    {
        if (filter_var($hubUrl, FILTER_VALIDATE_URL) === false) {
            $this->errorHandler->handleException(new InvalidGazeHubUrlException());
        }

        $this->hubUrl = rtrim($hubUrl, '/');
    }
}
