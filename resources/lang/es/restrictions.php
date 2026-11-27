<?php

return [
    'vpn_policy' => [
        'title' => 'Política de VPN / Proxy',
        'help' => 'Elige qué ocurre solo cuando se detecta positivamente una VPN o un proxy.',
        'label' => 'VPN / Proxy',
        'allow' => 'Permitir VPN / Proxy',
        'block' => 'Bloquear VPN / Proxy detectado',
        'unknown_help' => 'Una comprobación desconocida o fallida no cuenta como detección; se aplica la regla normal del país.',
        'reason' => 'Motivo del cambio',
        'note' => 'Nota del operador (opcional)',
        'last_changed' => 'Último cambio: :time · :user',
    ],
];
