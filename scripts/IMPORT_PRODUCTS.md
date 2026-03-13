# Import products from saltibarsciu-festivalis-fb.csv

The file is a product feed (e.g. Facebook/Topo centras) with **~105k products**. Use the converter script and then Magento’s import.

## 1. Convert CSV to Magento format

From the project root (or inside the `app` container):

```bash
# Create output directory
mkdir -p var/import

# Convert full file to one CSV (can be large)
php scripts/convert_feed_to_magento_import.php --output=var/import/products_import.csv

# Or create batched files (recommended for 105k rows) – e.g. 5000 per file
php scripts/convert_feed_to_magento_import.php --batch=5000 --output=var/import/products_import.csv
```

Batched run creates: `products_import_1.csv`, `products_import_2.csv`, … (about 21 files for 105k rows).

## 2. Import in Magento

### Option A: Admin (single file or small batches)

1. **System** → **Data Transfer** → **Import**.
2. **Entity Type**: **Products**.
3. **Import file**: upload `var/import/products_import.csv` (or one of the batched files).
4. Leave **Validation Strategy** as “Stop on Error” or switch to “Skip error entries” if you want to skip bad rows.
5. Click **Check Data**, then **Import**.

Repeat for each batched file if you used `--batch=5000`.

### Option B: Docker (run converter inside app container)

If the CSV is inside the project and the container has it mounted:

```bash
docker compose exec app php scripts/convert_feed_to_magento_import.php --batch=5000 --output=var/import/products_import.csv
```

Then in Admin: **System** → **Data Transfer** → **Import** → Products, and upload each generated file from `var/import/`.

## 3. Categories and images

- **Categories**: The script maps `custom_label_0` to the **categories** column. Create the same category names in **Catalog** → **Categories** before or after import, or import will attach only if names match.
- **Images**: The converter leaves **image columns empty** so the import does not fail. Feed image URLs contain commas (e.g. `fit=contain,quality=85`) which break Magento’s CSV parsing, and remote download often times out or is blocked. After importing products, add images by:
  - Using an import extension that supports **image import from URL**, or  
  - Manually uploading images in the product edit form, or  
  - Downloading images to `pub/media/import/` and running a second import with only `sku` and `base_image` (filenames).

## 4. Products not showing on the storefront

If products were imported but **none appear on the homepage or in the catalog**:

1. **Run the all-in-one fix** (assigns products to website, sets status = Enabled, visibility = Catalog+Search, reindexes):
   ```bash
   docker compose exec app php scripts/fix_products_visibility.php
   docker compose exec app php bin/magento cache:flush
   ```

2. **Refresh invalid cache types** (fixes the “Page Cache, Layouts invalidated” warning and helps products show):
   - **Admin**: **System** → **Cache Management** → select **Page Cache** and **Layouts** → **Actions** → **Refresh**.
   - **CLI** (flushes all cache, including Page Cache and Layouts):
   ```bash
   docker compose exec app php bin/magento cache:flush
   ```

3. **Reindex** (after import or category changes):
   ```bash
   docker compose exec app php bin/magento indexer:reindex
   ```

4. **Homepage**: The default Magento homepage is a CMS page and often shows no products. To show products on the homepage:
   - **Content** → **Pages** → edit the page used as “Home Page” → add a **Widget** (e.g. “Catalog Products List” or “Catalog New Products List”) in the content, or  
   - Open a **category** from the main menu (create categories under **Catalog** → **Categories** and assign products to them), or  
   - Go directly to a category URL, e.g. `/catalog/category/view/s/default-category/id/2/`.

Future imports that use the updated converter script include `_product_websites=base`, so new imports will be assigned to the default website automatically.

## 5. Large imports (105k rows)

- Use **batched files** (`--batch=5000`) and import one file at a time to avoid timeouts/memory limits.
- Increase PHP `memory_limit` and `max_execution_time` for the web server if needed.
- After import: **System** → **Index Management** → reindex **Catalog** (or run `bin/magento indexer:reindex`).

## 6. Column mapping (feed → Magento)

| Feed column     | Magento column   |
|-----------------|------------------|
| id              | sku (sanitized)  |
| title           | name             |
| description     | description      |
| price / sale_price | price, special_price |
| image_link      | base_image, small_image, thumbnail_image |
| custom_label_0  | categories       |
