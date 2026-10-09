<?php

namespace App\Services;

use RuntimeException;

/**
 * The hourly limit of AI overview generations is used up.
 */
class AiOverviewLimitException extends RuntimeException {}
