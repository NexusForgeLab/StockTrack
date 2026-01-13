# Store Stocks App (PHP + SQLite) — Docker Ready

## Features
- Login (admin / user roles)
- Admin: add new items or increment stock
- Users: take items (decrement) + transaction log
- Public: check stock (no login)
- Audit table for logged-in users with filters

## Run (Docker)
```bash
unzip store-stock-app.zip
cd store-stock-app
docker compose up -d --build
```

Open:
- http://YOUR_SERVER_IP:8092

## Install DB (one time)
Open once:
- http://YOUR_SERVER_IP:8092/install.php

Default users:
- admin / admin123
- user / user123

Then delete install.php:
```bash
rm install.php
```

## If you see: unable to open database file
```bash
mkdir -p data
sudo chown -R 33:33 data
sudo chmod -R 775 data
docker compose restart
```
