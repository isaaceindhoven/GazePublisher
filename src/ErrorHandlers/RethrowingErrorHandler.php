<?php

declare(strict_types=1);

namespace ISAAC\GazePublisher\ErrorHandlers;

use Exception;

class RethrowingErrorHandler implements ErrorHandler
{
    public function handleException(Exception $exception): void
    {
        throw $exception;
    }
}
