<?php

declare(strict_types=1);

namespace ISAAC\GazePublisher\ErrorHandlers;

use Exception;

class IgnoringErrorHandler implements ErrorHandler
{
    public function handleException(Exception $exception): void
    {
    }
}
