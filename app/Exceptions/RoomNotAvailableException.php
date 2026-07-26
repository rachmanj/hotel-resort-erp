<?php

namespace App\Exceptions;

use Exception;

class RoomNotAvailableException extends Exception
{
    public function __construct(string $message = 'The selected room is not available for the requested dates.')
    {
        parent::__construct($message);
    }
}
