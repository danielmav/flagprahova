<?php
declare(strict_types=1);

namespace App\Fisiere;

/**
 * Miniaturi WebP generate la cerere în `fisiere/mini/{lățime}/AAAA/LL/nume.webp`.
 * Prima cerere trece prin PHP (MiniaturaController); următoarele sunt servite
 * direct de Apache, fiindcă fișierul există pe disc (.htaccess: -f => servit).
 */
final class Miniatura
{
    public const LATIMI = [480, 1600];
    private const CALE_OK = '#^\d{4}/\d{2}/[A-Za-z0-9._-]+\.(jpe?g|png|webp)$#i';

    public function __construct(private string $dirFisiere) {}

    public static function caleMini(string $cale, int $latime): ?string
    {
        if (!in_array($latime, self::LATIMI, true) || !preg_match(self::CALE_OK, $cale) || str_contains($cale, '..')) { return null; }
        return 'mini/' . $latime . '/' . preg_replace('/\.[^.\/]+$/', '.webp', $cale);
    }

    /** Calea absolută a miniaturii (generată dacă lipsește) sau null. */
    public function asigura(string $cale, int $latime): ?string
    {
        $rel = self::caleMini($cale, $latime);
        if ($rel === null) { return null; }
        $dest = $this->dirFisiere . '/' . $rel;
        if (is_file($dest)) { return $dest; }
        $sursa = $this->dirFisiere . '/' . $cale;
        if (!is_file($sursa)) { return null; }
        $vechi = ini_get('memory_limit');
        ini_set('memory_limit', '512M');
        try {
            $im = @imagecreatefromstring((string) file_get_contents($sursa));
            if ($im === false) { return null; }
            $im = $this->orienteaza($im, $sursa);
            $w = imagesx($im); $h = imagesy($im);
            if ($w > $latime) {
                $nh = (int) round($h * $latime / $w);
                $nou = imagecreatetruecolor($latime, $nh);
                imagecopyresampled($nou, $im, 0, 0, 0, 0, $latime, $nh, $w, $h);
                imagedestroy($im); $im = $nou;
            }
            if (!is_dir(dirname($dest))) { mkdir(dirname($dest), 0775, true); }
            $ok = imagewebp($im, $dest, 82);
            imagedestroy($im);
            return $ok ? $dest : null;
        } finally {
            ini_set('memory_limit', (string) $vechi);
        }
    }

    private function orienteaza(\GdImage $im, string $sursa): \GdImage
    {
        if (!function_exists('exif_read_data') || !preg_match('/\.jpe?g$/i', $sursa)) { return $im; }
        $exif = @exif_read_data($sursa);
        $rot = match ((int) ($exif['Orientation'] ?? 1)) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
        if ($rot === 0) { return $im; }
        $r = imagerotate($im, $rot, 0);
        if ($r === false) { return $im; }
        imagedestroy($im);
        return $r;
    }
}
