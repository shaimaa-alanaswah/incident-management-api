<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rate Limits Per Plan Tier
    |--------------------------------------------------------------------------
    |
    | Requests allowed per tenant per minute, enforced by ThrottleByTenantTier.
    |
    */

    'rate_limits' => [
        'free' => 60,
        'pro' => 600,
        'enterprise' => 6000,
    ],

];