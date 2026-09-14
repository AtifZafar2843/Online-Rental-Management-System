<?php
/**
 * Online Rental Management System (ORMS)
 * Fine Configuration Settings
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 3.9, 4, 5 (Rule 9 & 12)
 */

declare(strict_types=1);

if (!function_exists('get_fine_config_path')) {
    function get_fine_config_path(): string {
        return __DIR__ . '/fine_settings.json';
    }
}

if (!function_exists('get_fine_settings')) {
    function get_fine_settings(): array {
        $path = get_fine_config_path();
        $defaults = [
            'rate_per_day'          => 150.00, // Standard daily late fee (₹)
            'max_deposit_multiplier'=> 2.0,    // Max fine cap (2 * security_deposit per Rule 9)
            'auto_refund_days'      => 7,      // Rule 12 auto-refund timeout (days after end_date)
            'updated_at'            => date('Y-m-d H:i:s')
        ];

        if (file_exists($path)) {
            $content = file_get_contents($path);
            if ($content) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    return array_merge($defaults, $decoded);
                }
            }
        }

        return $defaults;
    }
}

if (!function_exists('get_fine_rate')) {
    function get_fine_rate(): float {
        $settings = get_fine_settings();
        return (float) ($settings['rate_per_day'] ?? 150.00);
    }
}

if (!function_exists('set_fine_rate')) {
    function set_fine_rate(float $rate): bool {
        if ($rate <= 0) {
            throw new InvalidArgumentException("Fine rate must be greater than zero.");
        }
        $settings = get_fine_settings();
        $settings['rate_per_day'] = round($rate, 2);
        $settings['updated_at'] = date('Y-m-d H:i:s');
        return file_put_contents(get_fine_config_path(), json_encode($settings, JSON_PRETTY_PRINT)) !== false;
    }
}
