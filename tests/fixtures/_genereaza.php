<?php
declare(strict_types=1);

// Rulează o singură dată; fișierele generate se comit.
file_put_contents(__DIR__ . '/mic.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
file_put_contents(__DIR__ . '/mic.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
file_put_contents(__DIR__ . '/rau.php.pdf', "<?php echo 'x';");
