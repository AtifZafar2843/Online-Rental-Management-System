<?php
/**
 * Online Rental Management System (ORMS)
 * Payment Failed Exception
 */

declare(strict_types=1);

require_once __DIR__ . '/ORMSException.php';

class PaymentFailedException extends ORMSException {}
