#!/usr/bin/env bash
# Deploy de cod pe flagprahova.ro prin API-ul cPanel (UAPI), fără clicuri:
#   1. verifică că `main` local e curat și împins pe GitHub;
#   2. VersionControl/update      = „Update from Remote” (pull în ~/repositories/flagprahova);
#   3. VersionControlDeployment/create = „Deploy HEAD Commit” (rulează .cpanel.yml);
#   4. așteaptă rezultatul și verifică paginile cheie.
# Datele de acces: ~/.secrets/flagprahova.env (CPANEL_HOST, CPANEL_USER, CPANEL_TOKEN),
# citite literal (parola FTP are caractere speciale; NU se face `source`).
# Rulare: bash scripts/deploy.sh            (din rădăcina proiectului, în Git Bash)
set -euo pipefail
export MSYS_NO_PATHCONV=1

ENV_FILE="${USERPROFILE:-$HOME}/.secrets/flagprahova.env"
while IFS= read -r linie || [ -n "$linie" ]; do
  case "$linie" in ''|\#*) continue;; esac
  k=${linie%%=*}; v=${linie#*=}; v=${v%$'\r'}
  case "$k" in CPANEL_HOST|CPANEL_USER|CPANEL_TOKEN) printf -v "$k" '%s' "$v";; esac
done < "$ENV_FILE"
: "${CPANEL_HOST:?lipsește CPANEL_HOST}" "${CPANEL_USER:?lipsește CPANEL_USER}" "${CPANEL_TOKEN:?lipsește CPANEL_TOKEN}"

REPO="/home/$CPANEL_USER/repositories/flagprahova"
SITE="https://flagprahova.ro"

uapi() { # uapi Modul/functie [param=valoare ...]  → JSON pe stdout
  local fn=$1; shift
  local args=()
  for a in "$@"; do args+=(--data-urlencode "$a"); done
  curl -s --max-time 120 -G -H "Authorization: cpanel $CPANEL_USER:$CPANEL_TOKEN" \
    "https://$CPANEL_HOST:2083/execute/$fn" "${args[@]}"
}
camp() { php -r '$j=json_decode(stream_get_contents(STDIN),true); $v=$j; foreach(array_slice($argv,1) as $k){ $v=is_array($v)&&array_key_exists($k,$v)?$v[$k]:null; } echo is_scalar($v)?$v:json_encode($v);' "$@"; }

# 1. local curat și împins
[ -z "$(git status --porcelain)" ] || { echo "Working tree nu e curat. Commit întâi."; exit 1; }
git fetch -q origin main
LOCAL=$(git rev-parse HEAD); REMOTE=$(git rev-parse origin/main)
[ "$LOCAL" = "$REMOTE" ] || { echo "HEAD local ($LOCAL) diferă de origin/main ($REMOTE). Fă push."; exit 1; }
echo "Commit de urcat: $(git log --oneline -1)"

# 2. Update from Remote
R=$(uapi VersionControl/update "repository_root=$REPO" "branch=main")
[ "$(echo "$R" | camp status)" = "1" ] || { echo "Update eșuat: $(echo "$R" | camp errors)"; exit 1; }
HEAD_SRV=$(echo "$R" | camp data last_update identifier)
echo "Server după update: ${HEAD_SRV:0:7}"
[ "$HEAD_SRV" = "$LOCAL" ] || { echo "Serverul nu e pe commitul local."; exit 1; }

# 3. Deploy HEAD Commit
R=$(uapi VersionControlDeployment/create "repository_root=$REPO")
[ "$(echo "$R" | camp status)" = "1" ] || { echo "Deploy refuzat: $(echo "$R" | camp errors)"; exit 1; }
ID=$(echo "$R" | camp data deploy_id)
echo "Deploy pornit (id $ID), aștept..."

# 4. Așteaptă rezultatul (max ~3 min)
for i in $(seq 1 36); do
  sleep 5
  R=$(uapi VersionControlDeployment/retrieve)
  ST=$(echo "$R" | php -r '$j=json_decode(stream_get_contents(STDIN),true); foreach((array)($j["data"]??[]) as $d){ if((string)$d["deploy_id"]===$argv[1]){ $t=$d["timestamps"]??[]; echo isset($t["succeeded"])?"ok":(isset($t["failed"])?"failed":"running"); exit; } } echo "running";' "$ID")
  [ "$ST" = "running" ] || break
done
[ "$ST" = "ok" ] || { echo "Deploy: $ST. Vezi ~/.cpanel/logs/vc_*_git_deploy.log"; exit 1; }
echo "Deploy reușit."

# 5. Verificări
fail=0
for p in "" robots.txt sitemap.xml health admin_flag/login 2014-2020/cooperare 2021-2027/actiuni; do
  c=$(curl -s -o /dev/null -w '%{http_code}' "$SITE/$p"); echo "$c /$p"; [ "$c" = "200" ] || fail=1
done
for p in templates/ src/ .env vendor/; do
  c=$(curl -s -o /dev/null -w '%{http_code}' "$SITE/$p"); echo "$c /$p (trebuie != 200)"; [ "$c" != "200" ] || fail=1
done
[ $fail = 0 ] && echo "Verificări OK." || { echo "Verificări EȘUATE."; exit 1; }
