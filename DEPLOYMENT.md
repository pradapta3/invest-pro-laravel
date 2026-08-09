# Deployment — dompetijo.mbayar.my.id

Panduan hosting aplikasi ini di VPS `202.155.14.70` memakai Docker.

Susunan container:

```
Internet :443
    │
    ▼
  caddy ──────────► app ──────────► mysql
  (TLS,           (nginx +          (data, cache,
   Let's           php-fpm)          session, queue)
   Encrypt)          ▲   ▲
                     │   │
              queue ─┘   └─ scheduler
```

Semua service membaca satu file `.env` di root project. Laravel memakainya
lewat bind mount read-only, Docker Compose memakainya untuk substitusi
`${...}` di `docker-compose.yml`.

---

## 1. Siapkan DNS lebih dulu

Caddy meminta sertifikat Let's Encrypt saat pertama kali start, dan itu hanya
berhasil kalau nama domainnya sudah mengarah ke server. **Lakukan ini sebelum
`docker compose up`.**

Di panel DNS `mbayar.my.id`, tambahkan:

| Type | Name        | Value            | TTL |
|------|-------------|------------------|-----|
| A    | `dompetijo` | `202.155.14.70`  | 300 |

Verifikasi dari mana saja:

```bash
dig +short dompetijo.mbayar.my.id      # harus menjawab 202.155.14.70
```

Kalau masih kosong, tunggu propagasi dulu. Mencoba deploy berulang kali
sebelum DNS siap akan membuat Let's Encrypt menerapkan rate limit (5 kegagalan
per akun per hostname per jam).

---

## 2. Siapkan server

```bash
ssh root@202.155.14.70
```

Cek Docker sudah ada dan plugin `compose` terpasang:

```bash
docker --version
docker compose version      # harus jalan; kalau tidak: apt install docker-compose-plugin
```

Pastikan port 80 dan 443 belum dipakai proses lain:

```bash
ss -ltnp '( sport = :80 or sport = :443 )'
```

Kalau ada nginx/apache milik host yang menempel di sana, hentikan
(`systemctl disable --now nginx`) atau ikuti bagian
[Di belakang reverse proxy yang sudah ada](#di-belakang-reverse-proxy-yang-sudah-ada).

Buka firewall bila aktif:

```bash
ufw allow 80/tcp && ufw allow 443/tcp && ufw reload    # kalau pakai ufw
```

---

## 3. Ambil kode

```bash
mkdir -p /opt && cd /opt
git clone <URL_REPO> dompetijo
cd dompetijo
git checkout claude/mbayar-domain-hosting-setup-i9aq0t
```

---

## 4. Isi `.env`

```bash
cp .env.production.example .env
chmod 600 .env
nano .env
```

Yang **wajib** diisi (semua ditandai `<CHANGE_ME>`; `deploy.sh` menolak jalan
kalau masih tersisa):

| Variabel | Isi |
|---|---|
| `ACME_EMAIL` | email Anda, untuk notifikasi kegagalan renewal sertifikat |
| `DB_ROOT_PASSWORD` | `openssl rand -hex 24` |
| `DB_PASSWORD` | `openssl rand -hex 24` |
| `TELEGRAM_BOT_TOKEN` | dari @BotFather |
| `TELEGRAM_WEBHOOK_SECRET` | `openssl rand -hex 32` |
| `GEMINI_API_KEY` | dari https://aistudio.google.com/apikey |

`APP_KEY` dibiarkan kosong — `deploy.sh` yang mengisinya.

Yang sudah benar dan **jangan diubah** kecuali paham konsekuensinya:

- `APP_URL=https://dompetijo.mbayar.my.id` — dipakai untuk semua link absolut,
  termasuk link reset password.
- `DB_HOST=mysql` — nama service di `docker-compose.yml`, bukan `127.0.0.1`
  (yang di dalam container menunjuk ke container app itu sendiri).
- `DB_USERNAME` bukan `root` — image mysql menolak membuat `root` sebagai
  `MYSQL_USER`.
- `APP_DEBUG=false` — kalau on, stack trace beserta isi `.env` (termasuk token
  Telegram) tampil ke pengunjung saat ada error.
- `SESSION_SECURE_COOKIE=true` — situs ini HTTPS-only.
- `LOG_STACK=stderr` — log masuk ke `docker compose logs`, bukan menumpuk di
  `storage/logs/laravel.log` yang tidak ada yang merotasi.

> Catatan: Compose ikut membaca file ini, jadi hindari karakter `$` di dalam
> nilai password. Password hex dari `openssl rand -hex` aman.

---

## 5. Deploy

```bash
./deploy.sh
```

Script ini: memeriksa prasyarat, membuat `APP_KEY`, memperingatkan kalau DNS
belum mengarah ke server atau port 80/443 dipakai, build image, menyalakan
stack, menunggu semua container healthy, lalu — khusus deploy pertama —
menjalankan seeder.

Seeder terakhir (`AdminUserSeeder`) **mencetak password admin satu kali**:

```
Admin created — email: admin@idxinvest.test / password: xxxxxxxxxxxx
```

Simpan, lalu ganti setelah login pertama. Kalau terlewat, buat ulang lewat
tinker:

```bash
docker compose exec app php artisan tinker
>>> $u = App\Models\User::where('is_admin', true)->first();
>>> $u->update(['email' => 'admin@mbayar.my.id', 'password' => 'password-baru-anda']);
```

Buka https://dompetijo.mbayar.my.id — permintaan pertama bisa lambat beberapa
detik selama Caddy mengambil sertifikat.

---

## 6. Daftarkan webhook Telegram

Baru bisa dilakukan setelah situs hidup di HTTPS (Telegram menolak endpoint
tanpa sertifikat valid):

```bash
TOKEN=$(grep '^TELEGRAM_BOT_TOKEN=' .env | cut -d= -f2-)
SECRET=$(grep '^TELEGRAM_WEBHOOK_SECRET=' .env | cut -d= -f2-)

curl -X POST "https://api.telegram.org/bot${TOKEN}/setWebhook" \
     -d "url=https://dompetijo.mbayar.my.id/telegram/webhook" \
     -d "secret_token=${SECRET}"

curl -s "https://api.telegram.org/bot${TOKEN}/getWebhookInfo"
```

`getWebhookInfo` harus menunjukkan URL di atas dengan
`"pending_update_count": 0` dan tanpa `last_error_message`.

---

## Operasional harian

```bash
cd /opt/dompetijo

docker compose ps                      # status semua container
docker compose logs -f app             # log web + PHP
docker compose logs -f scheduler       # log update market data terjadwal
docker compose logs -f caddy           # log TLS / sertifikat

docker compose exec app php artisan <perintah>
docker compose exec mysql mysql -u root -p db_saham
```

### Deploy versi baru

```bash
git pull && ./deploy.sh
```

`deploy.sh` aman dijalankan berulang kali. Migrasi dijalankan otomatis oleh
container `app` saat start. Untuk deploy tanpa menyentuh skema:

```bash
RUN_MIGRATIONS=false ./deploy.sh
```

### Backup database

Volume `dompetijo_mysql-data` adalah satu-satunya tempat data hidup — image
bisa dibuang dan dibangun ulang kapan saja, data tidak.

```bash
docker compose exec -T mysql \
    mysqldump -u root -p"$(grep '^DB_ROOT_PASSWORD=' .env | cut -d= -f2-)" \
    --single-transaction --routines db_saham \
    | gzip > "backup-$(date +%F).sql.gz"
```

Restore:

```bash
gunzip -c backup-2026-08-09.sql.gz | docker compose exec -T mysql \
    mysql -u root -p"$(grep '^DB_ROOT_PASSWORD=' .env | cut -d= -f2-)" db_saham
```

### Menjalankan update market data manual

```bash
docker compose exec app php artisan idx:update-realtime-quotes
docker compose exec app php artisan idx:update-market-data
docker compose exec app php artisan idx:backfill-price-history --years=1
docker compose exec app php artisan idx:update-news-sentiment
docker compose exec app php artisan system:health-check
```

Jadwal otomatisnya ada di `routes/console.php` dan dijalankan container
`scheduler`. Kalau container itu mati, harga tidak akan pernah ter-update.

---

## Troubleshooting

**Sertifikat tidak terbit / situs tetap http**

```bash
docker compose logs caddy | tail -50
```

Penyebab yang hampir selalu benar: A record belum mengarah ke server, atau
port 80 diblokir firewall (validasi ACME HTTP-01 lewat port 80, bukan 443).

**Container `app` restart terus**

```bash
docker compose logs app | tail -80
```

Entrypoint sengaja gagal keras dan menyebutkan sebabnya — `APP_KEY` kosong,
`.env` tidak ter-mount, atau MySQL tak terjangkau.

**Halaman error 500 tanpa detail**

Itu memang perilaku `APP_DEBUG=false`. Detailnya ada di log:

```bash
docker compose logs -f app
```

Jangan menyalakan `APP_DEBUG` di server untuk mendiagnosis — isi `.env` ikut
tercetak ke browser.

**Redirect ke `http://` setelah login, atau mixed content**

Berarti header `X-Forwarded-Proto` dari Caddy tidak dipercaya. Cek
`TRUSTED_PROXIES` di `.env` mencakup subnet Docker
(`10.0.0.0/8,172.16.0.0/12,192.168.0.0/16`), lalu `docker compose up -d app`.

**Perubahan `.env` tidak terlihat**

Config di-cache saat container start. Restart container yang bersangkutan:

```bash
docker compose restart app queue scheduler
```

**Mulai benar-benar dari nol** (menghapus seluruh database):

```bash
docker compose down -v      # -v juga menghapus sertifikat Caddy
```

---

## Di belakang reverse proxy yang sudah ada

Kalau port 80/443 sudah dipakai proxy lain di server ini, jangan jalankan
container `caddy`. Buat `docker-compose.override.yml`:

```yaml
services:
  caddy:
    scale: 0          # jangan dijalankan
  app:
    ports:
      - "127.0.0.1:8080:8080"   # hanya loopback, tidak terekspos ke internet
```

Lalu arahkan proxy yang ada ke `127.0.0.1:8080`, dan pastikan ia meneruskan
`X-Forwarded-For`, `X-Forwarded-Proto` dan `X-Forwarded-Host` — Laravel
membutuhkan ketiganya (lihat `bootstrap/app.php`). Contoh untuk nginx host:

```nginx
location / {
    proxy_pass http://127.0.0.1:8080;
    proxy_set_header Host              $host;
    proxy_set_header X-Real-IP         $remote_addr;
    proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-Host  $host;
}
```

---

## Isi repo yang relevan

| File | Fungsi |
|---|---|
| `Dockerfile` | image aplikasi: build asset Vite, vendor Composer, runtime nginx + php-fpm |
| `docker-compose.yml` | definisi 5 service, network, volume |
| `docker/entrypoint.sh` | boot container: perbaiki permission, tunggu DB, migrate, warm cache, dispatch per role |
| `docker/nginx/nginx.conf` | konfigurasi nginx di dalam container app |
| `docker/php/php.ini` | limit PHP + OPcache produksi |
| `docker/php/php-fpm.conf` | tuning pool php-fpm |
| `docker/supervisord.conf` | menjalankan nginx dan php-fpm berdampingan |
| `docker/caddy/Caddyfile` | TLS otomatis + reverse proxy |
| `deploy.sh` | script deploy/rollout |
| `.env.production.example` | template `.env` untuk server |
