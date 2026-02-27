<?php

return [
    'public_token_ttl_minutes' => env('REPAIR_PUBLIC_TOKEN_TTL_MINUTES', 30),
    'require_invoice_before_pickup' => env('REPAIRS_REQUIRE_INVOICE_BEFORE_PICKUP', true),
];
