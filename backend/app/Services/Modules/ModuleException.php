<?php

namespace App\Services\Modules;

use RuntimeException;

/**
 * A Python module wasn't found, is set up wrongly, is off, or failed when it
 * ran. The message is for logs; `reason` is a short Finnish explanation that
 * can be shown to the user.
 */
class ModuleException extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $reason = null)
    {
        parent::__construct($message);
    }
}
