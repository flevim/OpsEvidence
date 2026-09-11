<?php

return [
    'version' => '1.0.0',

    'limits' => [
        'min_check_interval_seconds' => (int) env('OPSEVIDENCE_MIN_CHECK_INTERVAL_SECONDS', 60),
        'max_checks_per_account' => (int) env('OPSEVIDENCE_MAX_CHECKS_PER_ACCOUNT', 1000),
        'agent_evidence_max_items' => (int) env('OPSEVIDENCE_AGENT_EVIDENCE_MAX_ITEMS', 200),
        'webhook_payload_max_kb' => (int) env('OPSEVIDENCE_WEBHOOK_PAYLOAD_MAX_KB', 64),
    ],

    'collectors' => [
        'http' => [
            'timeout_seconds' => (int) env('OPSEVIDENCE_HTTP_CHECK_TIMEOUT_SECONDS', 10),
            'connect_timeout_seconds' => (int) env('OPSEVIDENCE_HTTP_CONNECT_TIMEOUT_SECONDS', 5),
            'max_redirects' => 5,
            'user_agent' => 'OpsEvidence/1.0 (+https://opsevidence.app)',
        ],
        'ssl' => [
            'timeout_seconds' => (int) env('OPSEVIDENCE_SSL_TIMEOUT_SECONDS', 10),
        ],
    ],

    'evidence' => [
        'retention_days' => (int) env('OPSEVIDENCE_EVIDENCE_RETENTION_DAYS', 90),
        'check_run_retention_days' => (int) env('OPSEVIDENCE_CHECK_RUN_RETENTION_DAYS', 90),
    ],

    'dashboard' => [
        'window_hours' => (int) env('OPSEVIDENCE_DASHBOARD_WINDOW_HOURS', 24),
        'recent_evidence_limit' => 20,
        'issue_limit' => 25,
    ],

    /*
     * Infrastructure Health Score.
     *
     * Los pesos suman 100. Cada componente se puntua de 0 a 100 y el score final
     * es la media ponderada de los componentes CON DATOS. Los componentes sin
     * datos se excluyen del calculo y se informan aparte: nunca se puntua un
     * cero por falta de informacion.
     *
     * Ver docs/health-score.md para la formula completa.
     */
    'health_score' => [
        'version' => '1.0',
        'weights' => [
            'availability' => 30,
            'backups' => 20,
            'resources' => 15,
            'containers' => 10,
            'ssl' => 10,
            'updates' => 10,
            'incidents' => 5,
        ],
        'bands' => [
            'excellent' => 90,
            'good' => 75,
            'attention' => 60,
            'risk' => 40,
        ],
    ],

    'reporting' => [
        'max_period_days' => 120,
        'default_period_days' => 30,
    ],
];
