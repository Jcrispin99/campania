<?php

declare(strict_types=1);

return [
    // Production accounts are created by an operator, rather than public sign-up.
    'public_registration' => env('CAMPANIA_PUBLIC_REGISTRATION', env('APP_ENV') !== 'production'),
];
