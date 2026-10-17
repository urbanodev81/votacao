<?php

return [
    // false em sistema com banco por tenant: publique as migrations
    // (`vendor:publish --tag=votacao-migrations`) para database/migrations/tenant.
    'carregar_migrations' => env('VOTACAO_CARREGAR_MIGRATIONS', true),

    // Invariante 7: hosts aceitos no link da manifestação. Só https.
    'hosts_de_rede' => ['instagram.com', 'www.instagram.com', 'facebook.com', 'www.facebook.com', 'm.facebook.com'],
];
