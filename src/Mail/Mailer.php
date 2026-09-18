<?php

declare(strict_types=1);

namespace App\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

/**
 * Singurul loc care trimite email. Două moduri, alese de `smtp_host`:
 *   gol       => scrie mesajul în `storage/logs/mail.log`. Nu trimite nimic.
 *   completat => SMTP real via PHPMailer.
 *
 * Modul „gol" e util pe dev (`env` = 'dev'/'local'), dar pe un mediu de
 * producție ar ascunde o configurare SMTP lipsă — `send()` ar întoarce `true`
 * deși nimic n-a plecat. De-aia `$env` decide dacă modul „gol" mai raportează
 * succes: în afara `dev`/`local`, `send()` întoarce `false` și scrie eroarea
 * în `error_log` + `storage/logs/mail_erori.log`, ca ecranele de admin să
 * poată spune onest că emailul n-a plecat.
 *
 * `CharSet = 'UTF-8'` e setat explicit: implicitul PHPMailer e iso-8859-1, care
 * transformă diacriticele românești în mojibake.
 */
final class Mailer
{
    /**
     * Valorile lui `$env` tratate ca „nu e producție" — modul „gol" rămâne
     * silențios (întoarce true). Orice altă valoare (inclusiv `prod`) e
     * tratată ca producție.
     */
    private const MEDII_NEPRODUCTIE = ['dev', 'local'];

    /**
     * Diagnostic manual: când e `true`, tipărește conversația SMTP brută
     * (`PHPMailer::SMTPDebug = 2`). Implicit `false` — nu se activează
     * niciodată din fluxul normal al aplicației.
     */
    public bool $smtpDebug = false;

    /** @param array<string,mixed> $config secțiunea 'mail' din settings */
    public function __construct(private array $config, private string $logPath, private string $env = 'local') {}

    private function esteProductie(): bool
    {
        return !in_array($this->env, self::MEDII_NEPRODUCTIE, true);
    }

    public function adminAddress(): string
    {
        return (string) $this->config['admin'];
    }

    /**
     * Trimite (sau loghează) un mesaj HTML. Nu aruncă niciodată: un SMTP căzut
     * nu trebuie să dărâme un formular — apelantul a salvat deja cererea în DB.
     *
     * @return bool true dacă a fost trimis/logat cu succes.
     */
    public function send(string $to, string $subject, string $htmlBody, ?string $replyTo = null, ?string $cc = null): bool
    {
        if ((string) $this->config['smtp_host'] === '') {
            // Corpul ajunge în mail.log DOAR pe dev. În producție ar însemna un
            // fișier plin de linkuri de resetare cu tokenul brut în clar, într-un
            // director care poate fi citit de orice altceva rulează pe cont.
            if ($this->esteProductie()) {
                error_log('[flagprahova][mail] SMTP_HOST gol in productie - mesajul NU a plecat catre ' . $to);
                $this->writeToErrorLog($to, 'SMTP_HOST gol in productie - mesajul nu a plecat');
                return false;
            }

            return $this->writeToLog($to, $subject, $htmlBody, $replyTo, $cc);
        }

        try {
            $m = new PHPMailer(true);
            $m->CharSet  = 'UTF-8';   // NU șterge: fără el, diacriticele ies mojibake.
            $m->Encoding = 'base64';

            $m->isSMTP();
            if ($this->smtpDebug) {
                $m->SMTPDebug   = 2;
                $m->Debugoutput = static function (string $mesaj): void {
                    echo $mesaj . "\n";
                };
            }
            $m->Host     = (string) $this->config['smtp_host'];
            $m->Port     = (int) $this->config['smtp_port'];
            $m->SMTPAuth = true;
            $m->Username = (string) $this->config['smtp_user'];
            $m->Password = (string) $this->config['smtp_pass'];
            $secure = (string) $this->config['smtp_secure'];
            if ($secure !== '') {
                $m->SMTPSecure = $secure; // 'tls' | 'ssl'
            }

            $m->setFrom((string) $this->config['from'], (string) $this->config['from_name']);
            $m->addAddress($to);
            if ($cc !== null && $cc !== '' && $cc !== $to) {
                $m->addCC($cc);
            }
            if ($replyTo !== null && $replyTo !== '') {
                $m->addReplyTo($replyTo);
            }

            $m->isHTML(true);
            $m->Subject = $subject;
            $m->Body    = $htmlBody;
            $m->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $htmlBody)));

            return $m->send();
        } catch (Throwable $e) {
            error_log('[flagprahova][mail] trimitere esuata catre ' . $to . ': ' . $e->getMessage());
            $this->writeToErrorLog($to, $e->getMessage());
            return false;
        }
    }

    /**
     * O linie per eșec în `storage/logs/mail_erori.log` — `error_log()` de PHP
     * e greu de găsit pe cPanel. Fără date sensibile: fără parolă, fără corpul
     * mesajului, doar destinatarul și motivul eșecului.
     */
    private function writeToErrorLog(string $to, string $motiv): void
    {
        $linie = sprintf("%s | catre: %s | %s\n", date('Y-m-d H:i:s'), $to, $motiv);

        $cale = dirname($this->logPath) . '/mail_erori.log';
        $dir  = dirname($cale);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents($cale, $linie, FILE_APPEND | LOCK_EX);
    }

    private function writeToLog(string $to, string $subject, string $htmlBody, ?string $replyTo, ?string $cc): bool
    {
        $entry = sprintf(
            "=== %s ===\nTo: %s\nCc: %s\nReply-To: %s\nFrom: %s <%s>\nSubject: %s\n\n%s\n\n",
            date('Y-m-d H:i:s'),
            $to,
            $cc ?? '-',
            $replyTo ?? '-',
            (string) $this->config['from_name'],
            (string) $this->config['from'],
            $subject,
            $htmlBody
        );

        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return file_put_contents($this->logPath, $entry, FILE_APPEND | LOCK_EX) !== false;
    }
}
