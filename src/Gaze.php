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
use ISAAC\GazePublisher\Exceptions\GazeEmitException;
use ISAAC\GazePublisher\Exceptions\GazeHubUrlInvalidException;
use JsonSerializable;

use function array_merge;
use function curl_close;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt;
use function filter_var;
use function json_encode;
use function rand;
use function substr;
use function time;
use function uniqid;

use const CURLINFO_HTTP_CODE;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_URL;
use const FILTER_VALIDATE_URL;

class Gaze
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
    private $maxTries;

    /**
     * @var bool
     */
    private $ignoreErrors;

    /**
     * @param string $hubUrl
     * @param string $privateKeyContent
     * @param int $maxTries
     * @param bool $ignoreErrors
     * @throws GazeHubUrlInvalidException
     */
    public function __construct(
        string $hubUrl,
        string $privateKeyContent,
        int $maxTries = 3,
        bool $ignoreErrors = false
    ) {
        $this->setHubUrl($hubUrl);
        $this->privateKeyContent = $privateKeyContent;
        $this->maxTries = $maxTries;
        $this->ignoreErrors = $ignoreErrors;
    }

    /**
     * @param string $topic
     * @param null|mixed $payload
     * @param string|null $role
     * @throws GazeEmitException
     */
    public function emit(string $topic, $payload = null, string $role = null): void
    {
        $httpCode = $this->sendToHub($topic, $payload, $role);
        $tries = 1;

        while ($httpCode !== 200 && ++$tries <= $this->maxTries) {
            $httpCode = $this->sendToHub($topic, $payload, $role);
        }

        if (!$this->ignoreErrors && $httpCode !== 200) {
            throw new GazeEmitException();
        }
    }

    /**
     * @param string[] $clientRoles
     * @param int $minutesValid
     * @return string
     */
    public function generateClientToken(array $clientRoles = [], int $minutesValid = 300): string
    {
        return $this->generateJwt(['roles' => $clientRoles], $minutesValid);
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
     */
    private function getCurl(string $url, $payload, array $headers)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        return $ch;
    }

    /**
     * @param array<string, mixed> $data
     * @param int $minutes
     * @return string
     */
    private function generateJwt(array $data, int $minutes): string
    {
        return JWT::encode(array_merge($data, [
            'exp' => time() + 60 * $minutes,
            'jti' => uniqid((string) rand(), true),
        ]), $this->privateKeyContent, 'RS256');
    }

    /**
     * @param string $hubUrl
     * @throws GazeHubUrlInvalidException
     */
    private function setHubUrl(string $hubUrl): void
    {
        if (filter_var($hubUrl, FILTER_VALIDATE_URL) === false) {
            throw new GazeHubUrlInvalidException();
        }
        if ($hubUrl[-1] === '/') {
            $hubUrl = substr($hubUrl, 0, -1);
        }
        $this->hubUrl = $hubUrl;
    }
}
