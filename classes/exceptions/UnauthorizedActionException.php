<?php
/**
 * Online Rental Management System (ORMS)
 * Unauthorized Action Exception
 * 
 * Thrown when a user attempts an operation prohibited by their assigned roles or permissions.
 */

declare(strict_types=1);

require_once __DIR__ . '/ORMSException.php';

class UnauthorizedActionException extends ORMSException {}
