<?php

declare(strict_types=1);

namespace ISAAC\GazePublisher\ErrorHandlers;

use Exception;

interface ErrorHandler
{
    public function handleException(Exception $exception): void;
}
