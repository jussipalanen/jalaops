<?php

namespace App\Services;

use RuntimeException;

/**
 * Nominatim couldn't look up an address (unreachable, failed or busy).
 */
class GeocoderException extends RuntimeException {}
