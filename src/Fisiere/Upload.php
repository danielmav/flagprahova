<?php
declare(strict_types=1);

namespace App\Fisiere;

use finfo;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;
use ZipArchive;

final class Upload
{
    public const EXTENSII = ['pdf','doc','docx','xls','xlsx','ppt','pptx','odt','jpg','jpeg','png','webp','zip'];

    /** MIME (din conținut) → extensie canonică. */
    private const MIME = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.oasis.opendocument.text' => 'odt',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/zip' => 'zip',
    ];

    public function __construct(private string $dir, private int $maxBytes) {}

    public static function esteImagine(string $mime): bool
    {
        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true);
    }

    public static function numeSigur(string $numeOriginal): string
    {
        $baza = basename(str_replace('\\', '/', $numeOriginal));
        $ext  = strtolower(pathinfo($baza, PATHINFO_EXTENSION));
        $nume = pathinfo($baza, PATHINFO_FILENAME);
        $slug = slugify($nume);
        return $slug . ($ext !== '' ? '.' . $ext : '');
    }

    public function salveaza(UploadedFileInterface $f): array
    {
        $numeOriginal = (string) ($f->getClientFilename() ?? 'fisier');
        $nu = fn(string $motiv) => ['cale' => null, 'nume_afisat' => $numeOriginal, 'mime' => '', 'marime' => 0, 'motiv' => $motiv];

        $err = $f->getError();
        if ($err === UPLOAD_ERR_NO_FILE) { return $nu('gol'); }
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) { return $nu('prea_mare'); }
        if ($err !== UPLOAD_ERR_OK) { return $nu('eroare'); }

        try {
            $stream = $f->getStream();
            $marime = (int) ($stream->getSize() ?? 0);
            if ($marime <= 0 || $marime > $this->maxBytes) {
                return $nu($marime > $this->maxBytes ? 'prea_mare' : 'eroare');
            }
            $stream->rewind();
            $cap = $stream->read(8192);
            $stream->rewind();
        } catch (Throwable) {
            return $nu('eroare');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($cap) ?: '';
        // Fișierele Office (docx/xlsx/pptx) și ODT sunt zip-uri: finfo poate întoarce application/zip.
        // Acceptăm fallback-ul pe extensia declarată DOAR dacă structura internă a arhivei
        // confirmă un document Office/ODT (nu orice .zip redenumit).
        $extDeclarata = strtolower(pathinfo($numeOriginal, PATHINFO_EXTENSION));
        if ($mime === 'application/zip' && in_array($extDeclarata, ['docx', 'xlsx', 'pptx', 'odt'], true)) {
            $mimeOffice = $this->detecteazaOffice($stream, $extDeclarata);
            if ($mimeOffice !== null) {
                $mime = $mimeOffice;
            }
        }
        if (!isset(self::MIME[$mime])) {
            return $nu('tip_nepermis');
        }
        $ext = self::MIME[$mime];

        $sub = date('Y/m');
        $dirAbs = rtrim($this->dir, '/\\') . '/' . $sub;
        if (!is_dir($dirAbs) && !mkdir($dirAbs, 0755, true) && !is_dir($dirAbs)) {
            return $nu('eroare');
        }
        $nume = pathinfo(self::numeSigur($numeOriginal), PATHINFO_FILENAME);
        $cand = $nume . '.' . $ext;
        for ($i = 2; is_file($dirAbs . '/' . $cand); $i++) {
            $cand = $nume . '-' . $i . '.' . $ext;
        }
        try {
            $f->moveTo($dirAbs . '/' . $cand);
        } catch (Throwable) {
            // TOCTOU: destinația a putut apărea între verificarea is_file() și moveTo().
            // O singură reîncercare, cu sufix aleator, înainte de a renunța.
            $cand = $nume . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
            try {
                $f->moveTo($dirAbs . '/' . $cand);
            } catch (Throwable) {
                return $nu('eroare');
            }
        }
        return ['cale' => $sub . '/' . $cand, 'nume_afisat' => $numeOriginal, 'mime' => $mime, 'marime' => $marime, 'motiv' => null];
    }

    /**
     * Verifică dacă un flux ZIP e de fapt un document Office (docx/xlsx/pptx) sau ODT,
     * după structura internă a arhivei — nu doar după extensia declarată de client.
     * Întoarce MIME-ul canonic dacă structura confirmă tipul, altfel null (rămâne application/zip).
     */
    private function detecteazaOffice(StreamInterface $stream, string $extDeclarata): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'fpzip');
        if ($tmp === false) {
            return null;
        }
        try {
            $stream->rewind();
            $fh = fopen($tmp, 'wb');
            if ($fh === false) {
                return null;
            }
            while (!$stream->eof()) {
                fwrite($fh, $stream->read(65536));
            }
            fclose($fh);
            $stream->rewind();

            $zip = new ZipArchive();
            if ($zip->open($tmp) !== true) {
                return null;
            }
            try {
                $ok = match ($extDeclarata) {
                    'docx', 'xlsx', 'pptx' => $zip->locateName('[Content_Types].xml') !== false,
                    'odt' => $zip->locateName('mimetype') !== false
                        && $zip->getFromName('mimetype') === 'application/vnd.oasis.opendocument.text',
                    default => false,
                };
            } finally {
                $zip->close();
            }
            return $ok ? (array_search($extDeclarata, self::MIME, true) ?: null) : null;
        } catch (Throwable) {
            return null;
        } finally {
            @unlink($tmp);
        }
    }
}
