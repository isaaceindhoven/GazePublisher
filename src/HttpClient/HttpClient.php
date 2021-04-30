<?php

declare(strict_types=1);

namespace ISAAC\GazePublisher\HttpClient;

interface HttpClient
{
    /**
     * @param string $url
     * @param string $body
     * @param string[] $headers
     * @return integer
     */
    public function post(string $url, string $body, array $headers): int;
}
