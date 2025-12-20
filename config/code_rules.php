<?php
// config/code_rules.php - PRAVIDLA PRO ANALÝZU KÓDU
declare(strict_types=1);

return [
    'no_debug_code' => [
        'pattern' => '/\b(var_dump|print_r|dd\(|console\.log)\b/',
        'message' => 'Nalezen debug kód',
        'severity' => 'warning',
        'extensions' => ['php', 'js']
    ],
    'no_sql_injection' => [
        'pattern' => '/\$\w+\s*\.?\s*["\']\s*SELECT.*["\']/i',
        'message' => 'Možná SQL injekce - použij prepared statements',
        'severity' => 'critical',
        'extensions' => ['php']
    ],
    'no_php_short_tags' => [
        'pattern' => '/<\?(?!php)/',
        'message' => 'Použij <?php místo <?',
        'severity' => 'warning',
        'extensions' => ['php']
    ],
    'no_echo_without_escape' => [
        'pattern' => '/echo\s+\$[a-zA-Z_]/',
        'message' => 'Echo proměnné bez escapování',
        'severity' => 'warning',
        'extensions' => ['php']
    ],
    'missing_strict_types' => [
        'pattern' => '/^<\?php\s*(?!declare\s*\(\s*strict_types\s*=\s*1\s*\))/m',
        'message' => 'Chybí declare(strict_types=1)',
        'severity' => 'warning',
        'extensions' => ['php']
    ]
];