<?php
/**
 * Online Rental Management System (ORMS)
 * Base Custom Exception Class
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 4 & Synopsis Section 11.2
 */

declare(strict_types=1);

class ORMSException extends Exception {
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
