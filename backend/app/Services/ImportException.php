<?php

namespace App\Services;

use RuntimeException;

/**
 * An import file can't be read; the message is shown to the user in Finnish.
 */
class ImportException extends RuntimeException {}
