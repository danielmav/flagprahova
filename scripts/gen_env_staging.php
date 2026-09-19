<?php
$l = file('materiale/dateconectare.txt', FILE_IGNORE_NEW_LINES);
$v = fn(int $n) => trim(preg_replace('/^[^:=]*[:=]\s*/', '', $l[$n-1]));
$smtpUser = trim($l[11]); $smtpPass = trim($l[12]);
$out = $v(18); $port = preg_replace('/\D/', '', $v(19));
$db = $v(23); $dbUser = $v(24); $dbPass = $v(25);
$salt = bin2hex(random_bytes(32));
$env = <<<ENV
APP_ENV=prod
APP_DEBUG=false
APP_URL=https://flagprahova.ro/nou
BASE_PATH=/nou
APP_INDEXABLE=false
ADMIN_PATH=admin
DB_HOST=localhost
DB_PORT=3306
DB_NAME=$db
DB_USER=$dbUser
DB_PASS="$dbPass"
DB_WP_NAME=
MAIL_FROM=noreply@flagprahova.ro
MAIL_FROM_NAME="FLAG Prahova"
MAIL_ADMIN=laura.m@flagprahova.ro
SMTP_HOST=$out
SMTP_PORT=$port
SMTP_USER=$smtpUser
SMTP_PASS="$smtpPass"
SMTP_SECURE=ssl
IP_SALT=$salt
TWIG_CACHE=true

ENV;
@mkdir('storage/migrare', 0775, true);
file_put_contents('storage/migrare/env-staging.txt', $env);
echo "SMTP_HOST=$out SMTP_PORT=$port DB_NAME=$db DB_USER=$dbUser DB_PASS_len=" . strlen($dbPass) . " SMTP_PASS_len=" . strlen($smtpPass) . "\n";
