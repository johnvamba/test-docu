<?php

namespace App\Services\Import;

use RuntimeException;

/** A user-facing import failure; the message is safe to show in the UI. */
class ImportException extends RuntimeException {}
