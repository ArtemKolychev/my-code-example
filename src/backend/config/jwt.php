<?php

return [
    'private_key_path' => env('JWT_PRIVATE_KEY_PATH', base_path('../../docker/keys/private.pem')),
    'public_key_path' => env('JWT_PUBLIC_KEY_PATH', base_path('../../docker/keys/public.pem')),
];
