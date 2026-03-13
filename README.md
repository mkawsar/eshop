# Topocentras E-shop (Magento 2)

Magento 2.4.7 e-commerce store with a Topocentras-style theme, product feed import, and loyalty (“Topo klubas”) pricing display.

## Tech stack

- **Magento 2.4.7** (PHP 8.2)
- **Docker**: nginx, PHP-FPM, MySQL 8, Elasticsearch 8, Redis
- **Theme**: Eshop Topocentras Style (custom frontend)

## Quick start

1. **Environment**  
   Copy or edit `.env` (see `.env.example` if present). Defaults work for local development.

2. **Start the stack**
   ```bash
   docker compose up -d --build
   ```

3. **Install Magento** (first time only)
   ```bash
   docker compose exec app php bin/magento setup:install \
     --base-url=http://localhost/ \
     --db-host=mysql \
     --db-name=eshop \
     --db-user=root \
     --db-password=password \
     --admin-firstname=Admin \
     --admin-lastname=User \
     --admin-email=admin@example.com \
     --admin-user=admin \
     --admin-password=admin123 \
     --elasticsearch-host=elasticsearch \
     --elasticsearch-port=9200
   ```
   Adjust `--base-url`, `--db-*`, and `--elasticsearch-host` to match your `.env` / `docker-compose.yml`.

4. **Apply theme**  
   In Admin: **Content → Configuration** → your store → **Design** → **Theme** = **Eshop Topocentras Style** → Save.

5. **Open the site**  
   - Storefront: http://localhost (or the port set in `APP_PORT`)  
   - Admin: http://localhost/admin_* (see `app/etc/env.php` for `backend.frontName`)


## Product feed import

Products can be imported from a Topocentras-style CSV feed (e.g. Google Drive export).

1. Place the source CSV in the project root (e.g. `saltibarsciu-festivalis-fb.csv`), or set the path in the script.
2. Convert to Magento import format:
   ```bash
   docker compose exec app php scripts/convert_feed_to_magento_import.php --batch=5000
   ```
   This writes files to `var/import/products_import_1.csv`, `products_import_2.csv`, etc.
3. In Magento Admin: **System → Data Transfer → Import**  
   - Entity Type: **Products**  
   - Import file: choose the generated CSV(s) and run the import.

The converter maps `price`, `sale_price`, and `member_price` from the feed. The **lowest** of the two discount prices (vs. regular price) is imported as Magento **special price** (used for “Topo klubo kaina” on the frontend).

## Topo klubo (loyalty) pricing

For products with a **special price** (loyalty discount), the theme shows:

- **Kaina** – regular price (strikethrough)
- **Topo klubo kaina** – discounted price for Topo klubas members
- **Nuolaida X%** – discount percentage

This is handled by the Topocentras theme override of the catalog price template and styles in `topocentras-custom.css`.

## Project structure (relevant parts)

| Path | Purpose |
|------|--------|
| `app/design/frontend/Eshop/topocentras/` | Topocentras theme (layouts, templates, CSS) |
| `scripts/convert_feed_to_magento_import.php` | Feed CSV → Magento product import CSV |
| `var/import/` | Generated import CSVs (gitignored) |
| `docker-compose.yml`, `Dockerfile` | Docker setup |
| `.env` | Local env (gitignored) |

## License

Proprietary. See your license terms.
