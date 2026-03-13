#!/usr/bin/env php
<?php
/**
 * Convert saltibarsciu-festivalis-fb.csv (Topocentras-style feed) to Magento 2 product import CSV.
 *
 * Usage (from project root):
 *   php scripts/convert_feed_to_magento_import.php [--batch=5000] [--output=var/import/products_import.csv]
 *
 * Then import in Magento Admin: System -> Data Transfer -> Import
 * Entity Type: Products, Import file: the generated CSV.
 *
 * For 105k+ rows, use --batch=5000 to create multiple files (products_import_1.csv, _2.csv, ...).
 */

$options = getopt('', ['batch::', 'output::']);
$batchSize = isset($options['batch']) ? (int) $options['batch'] : 0;
$outputPath = $options['output'] ?? 'var/import/products_import.csv';

$projectRoot = dirname(__DIR__);
$sourceFile = $projectRoot . '/saltibarsciu-festivalis-fb.csv';

if (!is_file($sourceFile)) {
    fwrite(STDERR, "Source file not found: {$sourceFile}\n");
    exit(1);
}

$outDir = dirname($projectRoot . '/' . $outputPath);
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

function parsePrice($str) {
    if ($str === null || $str === '') {
        return '';
    }
    $str = trim((string) $str);
    $str = str_replace([' ', 'EUR', '€'], '', $str);
    // Remove thousands separator (e.g. 1,081.00 -> 1081.00); leave comma for decimal
    $str = preg_replace('/(\d),(\d{3})(?:\.|$)/', '$1$2', $str);
    // Allow comma as decimal separator (e.g. 18,49 -> 18.49)
    if (preg_match('/^\d+,\d+$/', $str)) {
        $str = str_replace(',', '.', $str);
    } else {
        $str = str_replace(',', '', $str);
    }
    return is_numeric($str) ? $str : '';
}

function cleanDescription($str) {
    if ($str === null || $str === '') {
        return '';
    }
    $str = str_replace('&quot;', '"', $str);
    $str = strip_tags($str);
    return substr($str, 0, 65535); // Magento description limit
}

// Magento product import header. Image columns left empty: URLs contain commas that
// break Magento's parser. _product_websites=base so products show on storefront.
$magentoHeader = [
    'sku', 'name', 'description', 'short_description', 'price', 'special_price',
    'product_type', '_attribute_set', 'visibility', 'product_online',
    '_product_websites',
    'base_image', 'small_image', 'thumbnail_image',
    'categories', 'weight', 'tax_class_name',
    'manage_stock', 'is_in_stock', 'qty', 'url_key',
];

$fp = fopen($sourceFile, 'r');
if (!$fp) {
    fwrite(STDERR, "Cannot open: {$sourceFile}\n");
    exit(1);
}

$header = fgetcsv($fp);
if ($header === false || ($header[0] ?? '') !== 'id') {
    fwrite(STDERR, "Unexpected CSV header. Expected: id,title,description,...\n");
    exit(1);
}
// Map column names to 0-based indices (id=0, title=1, price=8, sale_price=7, member_price=10, etc.)
$col = array_flip(array_map('trim', $header));
$idx = function ($name) use ($col) { return $col[$name] ?? -1; };

$rowNum = 0;
$fileIndex = 1;
$outFp = null;
$openNewFile = function () use (&$outFp, &$fileIndex, $outputPath, $batchSize, $magentoHeader, $projectRoot) {
    if ($outFp) {
        fclose($outFp);
    }
    $path = $batchSize > 0
        ? preg_replace('/\.csv$/', "_{$fileIndex}.csv", $projectRoot . '/' . $outputPath)
        : $projectRoot . '/' . $outputPath;
    $outFp = fopen($path, 'w');
    if (!$outFp) {
        throw new RuntimeException("Cannot write: {$path}");
    }
    fputcsv($outFp, $magentoHeader);
    $fileIndex++;
    return $outFp;
};

$outFp = $openNewFile();

while (($row = fgetcsv($fp)) !== false) {
    $rowNum++;
    $get = function ($name) use ($row, $idx) {
        $i = $idx($name);
        return ($i >= 0 && isset($row[$i])) ? $row[$i] : '';
    };
    if (count($row) < 9) {
        continue;
    }

    $id = $get('id');
    $title = $get('title');
    $description = $get('description');
    $link = $get('link');
    $imageLink = $get('image_link');
    $priceStr = $get('price');       // column "price"
    $salePriceStr = $get('sale_price');
    $memberPriceStr = $get('member_price');
    $brand = $get('brand');
    $categoryLabel = $get('custom_label_0');

    // Regular price = main price; fallback to sale then member
    $price = parsePrice($priceStr) ?: parsePrice($salePriceStr) ?: parsePrice($memberPriceStr);
    // Special price = Topo klubo (loyalty) discount price: use member_price or sale_price when lower than regular
    $specialPrice = '';
    $sale = parsePrice($salePriceStr);
    $member = parsePrice($memberPriceStr);
    if ($price !== '' && $price > 0) {
        $candidates = array_filter([$member, $sale], function ($v) use ($price) {
            return $v !== '' && is_numeric($v) && (float) $v < (float) $price;
        });
        if (!empty($candidates)) {
            $specialPrice = (string) min(array_map('floatval', $candidates));
        }
    }
    // Magento requires price; use 0.00 if missing so row is valid
    if ($price === '') {
        $price = '0.00';
    }

    $sku = preg_replace('/[^a-zA-Z0-9\-_]/', '', $id) ?: 'SKU-' . $id;
    $urlKey = $link ? basename(parse_url($link, PHP_URL_PATH), '.html') : '';
    if ($urlKey === '') {
        $urlKey = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($title)));
        $urlKey = substr(trim($urlKey, '-'), 0, 250);
    }

    // Leave image columns empty so import does not fail. _product_websites=base so products appear on storefront.
    $magentoRow = [
        $sku,
        substr($title, 0, 255),
        cleanDescription($description),
        substr(strip_tags($description), 0, 255),
        $price,
        $specialPrice,
        'simple',
        'Default',
        'Catalog, Search',
        '1',
        'base',  // _product_websites (default website)
        '',      // base_image
        '',      // small_image
        '',      // thumbnail_image
        $categoryLabel,
        '1',
        'Taxable Goods',
        '1',
        '1',
        '999',
        $urlKey,
    ];

    if ($batchSize > 0 && $rowNum > 1 && ($rowNum - 1) % $batchSize === 0) {
        $outFp = $openNewFile();
    }

    fputcsv($outFp, $magentoRow);

    if ($rowNum % 10000 === 0) {
        fwrite(STDERR, "Processed {$rowNum} rows...\n");
    }
}

fclose($fp);
if ($outFp) {
    fclose($outFp);
}

echo "Done. Total rows: {$rowNum}. Output: " . ($batchSize > 0 ? "multiple files (batch {$batchSize})" : $outputPath) . "\n";
