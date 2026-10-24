<?php

namespace App\Exceptions;

use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class RejectedOperationalResponse extends RuntimeException
{
    public function __construct(public readonly Response $response)
    {
        parent::__construct('Operational response requires rollback.');
    }
}
