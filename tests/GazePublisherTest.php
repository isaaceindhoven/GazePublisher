<?php

declare(strict_types=1);

namespace ISAAC\GazePublisher\Test;

use ISAAC\GazePublisher\Exceptions\HubEmitRejectedException;
use ISAAC\GazePublisher\Exceptions\InvalidGazeHubUrlException;
use ISAAC\GazePublisher\Exceptions\InvalidPayloadException;
use ISAAC\GazePublisher\GazePublisher;
use ISAAC\GazePublisher\HttpClient\HttpClient;
use PHPUnit\Framework\TestCase;

use function base64_decode;
use function explode;
use function file_get_contents;
use function json_decode;
use function json_encode;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\exactly;
use function PHPUnit\Framework\once;

use const NAN;

class GazePublisherTest extends TestCase
{
    /**
     * @var string
     */
    private $privateKeyContent;

    public function __construct()
    {
        parent::__construct();
        $this->privateKeyContent = (string) file_get_contents(__DIR__ . '/assets/private.key');
    }

    public function testIfUrlIsValidated(): void
    {
        $this->expectException(InvalidGazeHubUrlException::class);
        new GazePublisher('NOT A VALID URL', $this->privateKeyContent);
    }

    public function testIfEmitIsCalled(): void
    {
        $httpMock = $this->createMock(HttpClient::class);
        $httpMock->expects(once())->method('post')->willReturn(200);

        $gaze = new GazePublisher(
            'http://localhost:3333',
            $this->privateKeyContent,
            3,
            null,
            $httpMock
        );

        $gaze->emit('ProductCreated', ['id' => 1]);
    }

    public function testIfEmitHasTheRightPayload(): void
    {
        $httpMock = $this->createMock(HttpClient::class);
        $httpMock->expects(once())->method('post')->with(
            'http://localhost:3333/event',
            json_encode(['payload' => ['id' => 1], 'topic' => 'ProductCreated', 'role' => null ])
        )->willReturn(200);

        $gaze = new GazePublisher(
            'http://localhost:3333',
            $this->privateKeyContent,
            3,
            null,
            $httpMock
        );

        $gaze->emit('ProductCreated', ['id' => 1]);
    }

    public function testIfEmitIsCalled3TimesIfFailed(): void
    {
        $this->expectException(HubEmitRejectedException::class);
        $httpMock = $this->createMock(HttpClient::class);
        $httpMock->expects(exactly(3))->method('post')->willReturn(400);

        $gaze = new GazePublisher(
            'http://localhost:3333',
            $this->privateKeyContent,
            3,
            null,
            $httpMock
        );

        $gaze->emit('ProductCreated', ['id' => 1]);
    }

    public function testIfPayloadCanNotBeJsonEncoded(): void
    {
        $this->expectException(InvalidPayloadException::class);
        $gaze = new GazePublisher('http://localhost:3333', $this->privateKeyContent);
        $gaze->emit('ProductCreated', NAN);
    }

    public function testIfClientTokenHasTheRolesInPayload(): void
    {
        $gaze = new GazePublisher('http://localhost:3333', $this->privateKeyContent);
        $token = $gaze->generateClientToken(['admin']);
        $token = (string) base64_decode(explode('.', $token)[1], true);
        $token = json_decode($token, true);
        assertEquals(['admin'], $token['roles']);
    }

    public function testIfClientTokenRolesCanBeNullInPayload(): void
    {
        $gaze = new GazePublisher('http://localhost:3333', $this->privateKeyContent);
        $token = $gaze->generateClientToken();
        $token = (string) base64_decode(explode('.', $token)[1], true);
        $token = json_decode($token, true);
        assertEquals([], $token['roles']);
    }
}
