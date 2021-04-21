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

namespace ISAAC\GazePublisher\HttpClient;

interface IHttpClient
{
    /**
     * @param string $url
     * @param string $body
     * @param string[] $headers
     * @return integer
     */
    public function post(string $url, string $body, array $headers): int;
}
