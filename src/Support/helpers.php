<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Bucharest');

if (!function_exists('e')) {
    function e(?string $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('slugify')) {
    /** „Apel lansare – Măsura 1 (rev.2)” → „apel-lansare-masura-1-rev-2” */
    function slugify(string $text): string
    {
        $map = ['ă'=>'a','â'=>'a','î'=>'i','ș'=>'s','ş'=>'s','ț'=>'t','ţ'=>'t',
                'Ă'=>'a','Â'=>'a','Î'=>'i','Ș'=>'s','Ş'=>'s','Ț'=>'t','Ţ'=>'t'];
        $t = strtr($text, $map);
        $t = function_exists('iconv') ? (iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) ?: $t) : $t;
        $t = strtolower($t);
        $t = preg_replace('/[^a-z0-9]+/', '-', $t) ?? '';
        return trim($t, '-') ?: 'intrare';
    }
}

if (!function_exists('ip_hash')) {
    /** Hash sărat al IP-ului (throttle, jurnal). null când lipsește sarea sau IP-ul. */
    function ip_hash(?string $ip): ?string
    {
        $salt = (string) ($_ENV['IP_SALT'] ?? '');
        if ($salt === '' || $ip === null || $ip === '') {
            return null;
        }
        return hash('sha256', $salt . '|' . $ip);
    }
}
