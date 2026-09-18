<?php
declare(strict_types=1);

namespace App\Fisiere;

use finfo;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

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
        // Fișierele Office (docx/xlsx/pptx) sunt zip-uri: finfo poate întoarce application/zip. Acceptăm după extensia declarată.
        $extDeclarata = strtolower(pathinfo($numeOriginal, PATHINFO_EXTENSION));
        if ($mime === 'application/zip' && in_array($extDeclarata, ['docx', 'xlsx', 'pptx', 'odt'], true)) {
            $mime = array_search($extDeclarata, self::MIME, true) ?: $mime;
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
            return $nu('eroare');
        }
        return ['cale' => $sub . '/' . $cand, 'nume_afisat' => $numeOriginal, 'mime' => $mime, 'marime' => $marime, 'motiv' => null];
    }
}
