# Why Catalog or categories don’t show in the menu

The main storefront navigation is built from **categories** that are:

1. **Under the store’s root category** (e.g. Default Category)
2. **Enabled** (Enable Category = Yes)
3. **Included in menu** (Include in Menu = Yes)

If no categories meet this, the menu has no catalog links. The theme adds a **“Catalog”** link that points to the root category so you can still open the catalog.

## How to show the full category tree in the menu

1. In Admin: **Catalog** → **Categories**.
2. Under **Default Category** (or your store root), create subcategories or edit existing ones.
3. For each category that should appear in the top menu:
   - Set **Enable Category** = Yes  
   - Set **Include in Menu** = Yes  
   - **Save**.
4. Assign products to those categories (e.g. **Products in Category** or re-import with the **categories** column).
5. **System** → **Cache Management** → **Flush Cache** (or run `bin/magento cache:flush`).

After that, the top navigation will list those categories (and the theme’s “Catalog” link will still go to the root category).

## Import and categories

The product import **categories** column only assigns products to categories that **already exist** in Magento (by name/path). It does not create categories. So:

- Create the categories first in **Catalog** → **Categories** (e.g. “Kompiuterinės ausinės”, “Ausinės laidinės”), with **Include in Menu** = Yes.
- Then import products with the same category names in the **categories** column so they are linked to those categories and appear in the menu and on category pages.
