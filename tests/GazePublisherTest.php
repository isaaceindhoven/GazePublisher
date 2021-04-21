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

namespace ISAAC\GazePublisher\Test;

use ISAAC\GazePublisher\GazePublisher;
use ISAAC\GazePublisher\HttpClient\IHttpClient;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\once;

class GazePublisherTest extends TestCase
{
    public function testIfEmitIsCalled()
    {
        $httpMock = $this->createMock(IHttpClient::class);
        $httpMock->expects(once())->method('post')->willReturn(200);

        $gaze = new GazePublisher(
            "http://localhost:3333",
            file_get_contents(__DIR__ . "/assets/private.key"),
            3,
            null,
            $httpMock
        );

        $gaze->emit("ProductCreated", ["id" => 1]);
    }

    public function testIfEmitHasTheRightPayload()
    {
    }

    public function testIfEmitIsCalled3TimesIfFailed()
    {
    }

    public function testIfClientTokenHasTheRolesInPayload()
    {
    }
}
