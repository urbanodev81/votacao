<?php

return [
    // false em sistema com banco por tenant: publique as migrations
    // (`vendor:publish --tag=votacao-migrations`) para database/migrations/tenant.
    'carregar_migrations' => env('VOTACAO_CARREGAR_MIGRATIONS', true),

    // Invariante 7: hosts aceitos no link da manifestação. Só https.
    'hosts_de_rede' => ['instagram.com', 'www.instagram.com', 'facebook.com', 'www.facebook.com', 'm.facebook.com'],

    // Filtro de palavras da fila de moderação: só marca "atenção" e sobe no
    // topo; nunca aprova nem rejeita sozinho. Cada sistema põe a sua lista.
    'palavras_de_atencao' => [],
];
