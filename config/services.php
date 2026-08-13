<?php

return [
    'bank' => ['owner' => env('BANK_OWNER'), 'card_number' => env('BANK_CARD_NUMBER'), 'name' => env('BANK_NAME')],
    'marzban' => ['allow_private_ips' => (bool) env('MARZBAN_ALLOW_PRIVATE_IPS', false)],
];
