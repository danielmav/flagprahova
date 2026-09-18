# FLAG Prahova

Situl Asociației FLAG Prahova (două perioade de programare) — Slim 4 + Twig + PDO, admin minimal, fără build step JS.

## Instalare locală

```bash
composer install
cp .env.example .env   # completează DB_* și IP_SALT
php database/migrate.php
php database/seed.php
php database/create_admin.php email@exemplu.ro "Nume Prenume" parola
```

Servește cu Laragon / Apache pe `http://flagprahova.test`, sau cu serverul built-in PHP (folosind `router.php`, copiat separat — vezi `CLAUDE.md`).

## Admin

`http://flagprahova.test/admin/login` (calea e configurabilă din `ADMIN_PATH` în `.env`).

## Documentație

Spec: `docs/superpowers/specs/2026-09-18-flagprahova-site-nou-design.md`
Planuri: `docs/superpowers/plans/`
Note de implementare pentru Claude: `CLAUDE.md`
