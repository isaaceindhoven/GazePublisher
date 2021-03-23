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

namespace GazePHP;

use Firebase\JWT\JWT;
use GazePHP\Exceptions\GazeEmitException;
use GazePHP\Exceptions\GazeHubUrlInvalidException;
use GazePHP\Exceptions\PrivateKeyNotValidException;
use JsonSerializable;

use function curl_close;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt;
use function file_exists;
use function file_get_contents;
use function filter_var;
use function json_encode;
use function strpos;
use function substr;
use function time;

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
     * @param string $privateKey Can be passed as the **key path** location or **file contents**.
     */
    public function __construct(
        string $hubUrl,
        string $privateKey,
        int $maxTries = 3,
        bool $ignoreErrors = false
    ) {
        $this->setHubUrl($hubUrl);
        $this->setPrivateKey($privateKey);
        $this->maxTries = $maxTries;
        $this->ignoreErrors = $ignoreErrors;
    }

    /**
     * @param array|JsonSerializable $payload
     */
    public function emit(string $name, $payload, string $role = null): void
    {
        $httpCode = $this->sendEvent($name, $payload, $role);
        $tries = 1;

        while ($httpCode !== 200 && ++$tries <= $this->maxTries) {
            $httpCode = $this->sendEvent($name, $payload, $role);
        }

        if (!$this->ignoreErrors && $httpCode !== 200) {
            throw new GazeEmitException();
        }
    }

    /**
     * @param array|JsonSerializable $payload
     */
    private function sendEvent(string $name, $payload, string $role = null): int
    {
        $jwt = JWT::encode([
            'role' => 'server',
            'exp' => $this->timestampAfterMinutes(1),
        ], $this->privateKeyContent, 'RS256');

        $ch = $this->getCurl(
            $this->hubUrl . '/event',
            ['payload' => $payload, 'topic' => $name, 'role' => $role ],
            ['Content-Type:application/json', 'Authorization: Bearer ' . $jwt ]
        );
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode;
    }

    /**
     * @param array|JsonSerializable $payload
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

    public function generateClientToken(array $clientRoles = [], int $minutesValid = 300): string
    {
        return JWT::encode([
            'roles' => $clientRoles,
            'jti' => uniqid((string) rand(), true),
            'exp' => $this->timestampAfterMinutes($minutesValid),
        ], $this->privateKeyContent, 'RS256');
    }

    private function timestampAfterMinutes(int $minutes = 0): int
    {
        return time() + 60 * $minutes;
    }

    private function setHubUrl(string $hubUrl)
    {
        if (filter_var($hubUrl, FILTER_VALIDATE_URL) === false) {
            throw new GazeHubUrlInvalidException();
        }
        if ($hubUrl[-1] === '/') {
            $hubUrl = substr($hubUrl, 0, -1);
        }
        $this->hubUrl = $hubUrl;
    }

    private function setPrivateKey(string $privateKey)
    {
        if (strpos($privateKey, '-----BEGIN RSA PRIVATE KEY-----') !== 0) {
            if (file_exists($privateKey)) {
                $privateKey = file_get_contents($privateKey);
            } else {
                throw new PrivateKeyNotValidException();
            }
        }

        $this->privateKeyContent = $privateKey;
    }
}
