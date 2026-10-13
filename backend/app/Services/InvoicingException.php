<?php

namespace App\Services;

use RuntimeException;

/**
 * An invoicing action isn't allowed; the message is shown to the user in Finnish.
 */
class InvoicingException extends RuntimeException {}
