<?php

declare(strict_types=1);

namespace GazePHP;

use Firebase\JWT\JWT;
use Ramsey\Uuid\Uuid;

use function curl_close;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt;
use function json_encode;
use function time;

use const CURLINFO_HTTP_CODE;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_URL;

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

    public function __construct(
        string $hubUrl,
        string $privateKeyContent,
        int $maxTries = 3,
        bool $ignoreErrors = false
    ) {
        $this->hubUrl = $hubUrl;
        $this->privateKeyContent = $privateKeyContent;
        $this->maxTries = $maxTries;
        $this->ignoreErrors = $ignoreErrors;
    }

    public function emit(string $name, array $payload, string $role = null): void
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

    private function sendEvent(string $name, array $payload, string $role = null): int
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
     * @return resource
     */
    private function getCurl(string $url, array $payload, array $headers)
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
        $uuid = Uuid::uuid4();

        return JWT::encode([
            'roles' => $clientRoles,
            'jti' => $uuid->toString(),
            'exp' => $this->timestampAfterMinutes($minutesValid),
        ], $this->privateKeyContent, 'RS256');
    }

    private function timestampAfterMinutes(int $minutes = 0): int
    {
        return time() + 60 * $minutes;
    }
}
