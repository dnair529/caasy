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

## Deploying this repo

Same pattern as deepak-chat: on push to `main`, GitHub Actions rsyncs `server/`
to the VPS `caasy/` directory using the existing `VPS_SSH_KEY` secret.
