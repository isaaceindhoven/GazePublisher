<?php

/**
 *   Do not remove or alter the notices in this preamble.
 *   This software code regards ISAAC Standard Software.
 *   Copyright © 2021 ISAAC and/or its affiliates.
 *   www.isaac.nl All rights reserved. License grant and user rights and obligations
 *   according to applicable license agreement. Please contact sales@isaac.nl for
 *   questions regarding license and user rights.
 */

declare(strict_types=1);

namespace ISAAC\GazePublisher;

use Firebase\JWT\JWT;
use ISAAC\GazePublisher\Exceptions\InvalidGazeHubUrlException;
use ISAAC\GazePublisher\ErrorHandlers\IErrorHandler;
use ISAAC\GazePublisher\ErrorHandlers\RethrowingErrorHandler;
use ISAAC\GazePublisher\Exceptions\HubEmitRejectedException;
use ISAAC\GazePublisher\Exceptions\InvalidPayloadException;
use JsonException;

use function array_merge;
use function curl_close;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt;
use function filter_var;
use function json_encode;
use function rand;
use function time;
use function uniqid;

use const CURLINFO_HTTP_CODE;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_URL;
use const FILTER_VALIDATE_URL;

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
     * @var IErrorHandler
     */
    private $errorHandler;

    /**
     * @param string $hubUrl
     * @param string $privateKeyContent
     * @param int $maxRetries
     * @param IErrorHandler $errorHandler
     * @throws InvalidGazeHubUrlException
     */
    public function __construct(
        string $hubUrl,
        string $privateKeyContent,
        int $maxRetries = 3,
        IErrorHandler $errorHandler = null
    ) {
        $this->setHubUrl($hubUrl);
        $this->privateKeyContent = $privateKeyContent;
        $this->maxRetries = $maxRetries;
        if ($errorHandler === null){
            $this->errorHandler = new RethrowingErrorHandler();
        }else{
            $this->errorHandler = $errorHandler;
        }

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
     */
    private function sendToHub(string $topic, $payload, string $role = null): int
    {
        $jwt = $this->generateJwt(['role' => 'server'], 1);

        $ch = $this->getCurl(
            $this->hubUrl . '/event',
            ['payload' => $payload, 'topic' => $topic, 'role' => $role ],
            ['Content-Type: application/json', 'Authorization: Bearer ' . $jwt ]
        );
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode;
    }

    /**
     * @param string $url
     * @param mixed $payload
     * @param string[] $headers
     * @return resource
     * @throws JsonException
     */
    private function getCurl(string $url, $payload, array $headers)
    {
        try{
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            return $ch;
        }catch(JsonException $e){
            $this->errorHandler->handleException(new InvalidPayloadException());
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
