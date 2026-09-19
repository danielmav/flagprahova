CREATE TABLE IF NOT EXISTS sectiuni (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug         VARCHAR(20)  NOT NULL UNIQUE,
  titlu        VARCHAR(120) NOT NULL,
  subtitlu     VARCHAR(255) NOT NULL DEFAULT '',
  acasa_html   MEDIUMTEXT   NULL,
  hero_imagine VARCHAR(255) NULL,
  ordine       TINYINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fisiere (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nume_afisat  VARCHAR(255) NOT NULL,
  cale         VARCHAR(255) NOT NULL UNIQUE,
  mime         VARCHAR(100) NOT NULL,
  marime       INT UNSIGNED NOT NULL DEFAULT 0,
  incarcat_la  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  incarcat_de  INT UNSIGNED NULL,
  legacy_url   VARCHAR(500) NULL,
  KEY idx_fisiere_cale_prefix (cale(7)),
  KEY idx_fisiere_legacy (legacy_url(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meniu (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sectiune_id   INT UNSIGNED NOT NULL,
  parent_id     INT UNSIGNED NULL,
  ordine        INT UNSIGNED NOT NULL DEFAULT 0,
  titlu         VARCHAR(255) NOT NULL,
  slug          VARCHAR(160) NOT NULL,
  tip           ENUM('pagina','document','dosar','link','galerie') NOT NULL DEFAULT 'document',
  continut_html MEDIUMTEXT   NULL,
  fisier_id     INT UNSIGNED NULL,
  url           VARCHAR(500) NULL,
  sablon        ENUM('standard','contact') NOT NULL DEFAULT 'standard',
  vizibil       TINYINT(1)   NOT NULL DEFAULT 1,
  publicat_la   DATE         NULL,
  legacy_id     INT UNSIGNED NULL,
  creat_la      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  modificat_la  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_meniu_slug (sectiune_id, slug),
  KEY idx_meniu_parinte (sectiune_id, parent_id, ordine),
  KEY idx_meniu_legacy (legacy_id),
  CONSTRAINT fk_meniu_sectiune FOREIGN KEY (sectiune_id) REFERENCES sectiuni(id),
  CONSTRAINT fk_meniu_parent   FOREIGN KEY (parent_id)   REFERENCES meniu(id) ON DELETE CASCADE,
  CONSTRAINT fk_meniu_fisier   FOREIGN KEY (fisier_id)   REFERENCES fisiere(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS galerie_imagini (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  meniu_id  INT UNSIGNED NOT NULL,
  fisier_id INT UNSIGNED NOT NULL,
  ordine    INT UNSIGNED NOT NULL DEFAULT 0,
  legenda   VARCHAR(255) NOT NULL DEFAULT '',
  KEY idx_gal_meniu (meniu_id, ordine),
  CONSTRAINT fk_gal_meniu  FOREIGN KEY (meniu_id)  REFERENCES meniu(id)   ON DELETE CASCADE,
  CONSTRAINT fk_gal_fisier FOREIGN KEY (fisier_id) REFERENCES fisiere(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS utilizatori (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(190) NOT NULL UNIQUE,
  nume          VARCHAR(120) NOT NULL DEFAULT '',
  parola_hash   VARCHAR(255) NOT NULL DEFAULT '',
  ultimul_login DATETIME NULL,
  creat_la      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parola_tokens (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  utilizator_id  INT UNSIGNED NOT NULL,
  token_hash     CHAR(64) NOT NULL UNIQUE,
  expira_la      DATETIME NOT NULL,
  folosit_la     DATETIME NULL,
  creat_la       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ptok_user FOREIGN KEY (utilizator_id) REFERENCES utilizatori(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_incercari (
  id      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip_hash CHAR(64) NOT NULL,
  scope   VARCHAR(20) NOT NULL DEFAULT 'admin',
  la      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_login (ip_hash, scope, la)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS setari (
  cheie   VARCHAR(80) NOT NULL PRIMARY KEY,
  valoare TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mesaje_contact (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  sectiune_id  INT UNSIGNED NULL,
  nume         VARCHAR(120) NOT NULL,
  email        VARCHAR(190) NOT NULL,
  mesaj        TEXT NOT NULL,
  ip_hash      CHAR(64) NULL,
  trimis_la    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  email_trimis TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
