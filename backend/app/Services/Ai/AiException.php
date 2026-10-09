<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * An AI provider call failed or returned an unusable reply.
 */
class AiException extends RuntimeException {}
