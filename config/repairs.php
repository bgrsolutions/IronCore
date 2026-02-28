<?php

return [
    'public_token_ttl_minutes' => env('REPAIR_PUBLIC_TOKEN_TTL_MINUTES', 30),
    'require_invoice_before_pickup' => env('REPAIRS_REQUIRE_INVOICE_BEFORE_PICKUP', true),
    'time_leak_threshold_minutes' => (int) env('REPAIRS_TIME_LEAK_THRESHOLD_MINUTES', 15),
    'require_labour_if_time_logged' => filter_var(env('REPAIRS_REQUIRE_LABOUR_IF_TIME_LOGGED', true), FILTER_VALIDATE_BOOL),
    'manager_override_requires_reason' => filter_var(env('REPAIRS_MANAGER_OVERRIDE_REQUIRES_REASON', true), FILTER_VALIDATE_BOOL),
    'labour_rate_per_hour_net' => (float) env('REPAIRS_LABOUR_RATE_PER_HOUR_NET', 60.00),
    'default_tax_rate' => (float) env('REPAIRS_DEFAULT_TAX_RATE', 7.0),
];
