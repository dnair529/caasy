# caasy

Agent-to-web bridge for **https://dnair.us/caasy/** — lets an agent (Hermes on Ubuntu
at dnair529@10.0.0.164, or anything else) create and push files, folders, and
directories to the public web without hand-editing the VPS.

## What's here

- `bin/caasy` — CLI for the agent. Two transports:
  - `push` (rsync over SSH) — full folders, deletes stale files, needs an SSH key.
  - `send` (HTTPS POST) — token-authed, works from anywhere, no SSH key needed.
- `server/` — what lives at `https://dnair.us/caasy/`:
  - `index.html` — drag-and-drop web UI (talk to your agent here, drop files in).
  - `upload.php` — token-authed upload endpoint. Blocks path traversal, refuses to overwrite itself.

## Install (Ubuntu agent)

```bash
git clone https://github.com/dnair529/caasy.git && cd caasy
mkdir -p ~/.config/caasy
cat > ~/.config/caasy/config.env <<'EOF'
CAASY_HOST=dnair529@dnair.us
CAASY_ROOT=/var/www/dnairus/caasy
CAASY_URL=https://dnair.us/caasy
CAASY_TOKEN=<set-on-server>
EOF
chmod 600 ~/.config/caasy/config.env
```

## Usage

```bash
./bin/caasy push ./my-site/            # rsync folder → https://dnair.us/caasy/my-site/
./bin/caasy push ./report.pdf docs/    # single file into docs/
./bin/caasy send ./assets/ assets/     # HTTPS route, token required
./bin/caasy list                       # what's live
```

## Server setup (one-time, on dnair.us VPS)

1. `deploy.yml`-style: rsync `server/` → `/var/www/dnairus/caasy/`.
2. Set the token in the PHP-FPM pool env (or `/etc/php/*/fpm/pool.d/www.conf`):
   `env[CAASY_UPLOAD_TOKEN] = <long-random-string>`
3. `systemctl reload php*-fpm`

## Token rotation & revocation

The upload token lives in the PHP-FPM pool env on the VPS as `CAASY_UPLOAD_TOKEN`
and is read by `server/upload.php` at request time. Rotate it whenever it may have
leaked (it was shared in chat / with other agents).

Rotate (takes effect immediately, no file changes needed):

1. Generate a new token on the VPS:
   `openssl rand -hex 24`
2. Edit the PHP-FPM pool file that contains the current
   `env[CAASY_UPLOAD_TOKEN] = ...` line (usually `/etc/php/*/fpm/pool.d/www.conf`
   or a dedicated pool file) and replace the value.
3. Reload PHP-FPM: `systemctl reload php*-fpm`
4. Update every client that holds the token:
   - Mac: `~/projects/caasy/.upload-token`
   - Any agent's `CAASY_TOKEN` / config env
5. Verify: `curl -s -X POST "https://dnair.us/caasy/upload.php?path=test-rotate.txt" \
   -H "X-Auth-Token: <NEW_TOKEN>" -F "file=@README.md"` → expect `{"ok":true,...}`.
   Also confirm the OLD token now returns 401.

Revoke (lock everyone out, no replacement):

1. Comment out the `env[CAASY_UPLOAD_TOKEN]` line in the pool file and reload
   PHP-FPM. `upload.php` then fails closed with HTTP 500
   ("Server not configured") for all requests.
2. For a harder lockout, also remove the nginx
   `location = /caasy/upload.php` block and `nginx -s reload` (returns 404).
3. Clients keep failing until a new token is set — there is no grace period.

Never store the token in git. Keep it only in the pool file, local token files,
and agent env config.

## Deploying this repo

Same pattern as deepak-chat: on push to `main`, GitHub Actions rsyncs `server/`
to the VPS `caasy/` directory using the existing `VPS_SSH_KEY` secret.
