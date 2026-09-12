<?php
/**
 * Online Rental Management System (ORMS)
 * Duplicate Review Exception
 */

declare(strict_types=1);

require_once __DIR__ . '/ORMSException.php';

class DuplicateReviewException extends ORMSException {}
