#!/usr/bin/env php
<?php
/**
 * Diagnose and fix products not showing on storefront:
 * - Assign all simple products to default website (base)
 * - Set status = Enabled (1)
 * - Set visibility = Catalog, Search (4)
 * - Reindex
 *
 * Run: docker compose exec app php scripts/fix_products_visibility.php
 */

use Magento\Framework\App\Bootstrap;

$projectRoot = dirname(__DIR__);
require $projectRoot . '/app/bootstrap.php';

$bootstrap = Bootstrap::create($projectRoot, $_SERVER);
$obj = $bootstrap->getObjectManager();
$state = $obj->get(\Magento\Framework\App\State::class);
try {
    $state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
} catch (\Exception $e) {
    // already set
}

$resource = $obj->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resource->getConnection();

$productTable = $resource->getTableName('catalog_product_entity');
$websiteTable = $resource->getTableName('catalog_product_website');
$eavTable = $resource->getTableName('catalog_product_entity_int');
$eavAttr = $resource->getTableName('eav_attribute');
$entityType = $resource->getTableName('eav_entity_type');

// Get entity_type_id for catalog_product
$entityTypeId = $connection->fetchOne(
    $connection->select()->from($entityType, ['entity_type_id'])->where('entity_type_code = ?', 'catalog_product')
);

// Get attribute IDs for status and visibility
$attrCodes = ['status' => 1, 'visibility' => 4]; // 1=Enabled, 4=Catalog+Search
$attrIds = [];
foreach (array_keys($attrCodes) as $code) {
    $attrIds[$code] = $connection->fetchOne(
        $connection->select()
            ->from($eavAttr, ['attribute_id'])
            ->where('entity_type_id = ?', $entityTypeId)
            ->where('attribute_code = ?', $code)
    );
}

// Default website
$websiteId = 1;
try {
    $websiteRepository = $obj->get(\Magento\Store\Api\WebsiteRepositoryInterface::class);
    $website = $websiteRepository->get('base');
    $websiteId = (int) $website->getId();
} catch (\Exception $e) {
    // use 1
}

echo "--- Product visibility fix ---\n";
echo "Website ID: {$websiteId} (base)\n";

// 1) Count products
$totalProducts = (int) $connection->fetchOne(
    $connection->select()->from($productTable, [new \Magento\Framework\DB\Sql\Expression('COUNT(*)')])->where('type_id = ?', 'simple')
);
echo "Total simple products: {$totalProducts}\n";

if ($totalProducts === 0) {
    echo "No simple products in catalog. Import products first.\n";
    exit(1);
}

// 2) Assign to website
$onWebsite = $connection->fetchCol(
    $connection->select()->from($websiteTable, ['product_id'])->where('website_id = ?', $websiteId)
);
$onWebsite = array_flip($onWebsite);
$toAssign = [];
$productIds = $connection->fetchCol(
    $connection->select()->from($productTable, ['entity_id'])->where('type_id = ?', 'simple')
);
foreach ($productIds as $id) {
    if (!isset($onWebsite[$id])) {
        $toAssign[] = $id;
    }
}
if (!empty($toAssign)) {
    foreach (array_chunk($toAssign, 500) as $chunk) {
        foreach ($chunk as $pid) {
            $connection->insertOnDuplicate($websiteTable, ['product_id' => $pid, 'website_id' => $websiteId]);
        }
    }
    echo "Assigned " . count($toAssign) . " product(s) to website.\n";
} else {
    echo "All products already on website.\n";
}

// 3) Set status = 1 (Enabled) and visibility = 4 (Catalog, Search) for all simple products
$storeId = 0; // default scope
foreach (['status' => 1, 'visibility' => 4] as $code => $value) {
    $attrId = $attrIds[$code] ?? null;
    if (!$attrId) {
        echo "Warning: attribute '{$code}' not found, skipping.\n";
        continue;
    }
    foreach ($productIds as $productId) {
        $connection->insertOnDuplicate(
            $eavTable,
            [
                'attribute_id' => $attrId,
                'store_id' => $storeId,
                'entity_id' => $productId,
                'value' => $value,
            ],
            ['value']
        );
    }
    echo "Set {$code} = {$value} for all products.\n";
}

// 4) Reindex
echo "Running indexers...\n";
$indexerFactory = $obj->get(\Magento\Indexer\Model\IndexerFactory::class);
$indexerIds = ['catalog_product_price', 'catalog_product_attribute', 'cataloginventory_stock', 'catalog_search_fulltext', 'catalog_category_product'];
foreach ($indexerIds as $id) {
    try {
        $indexer = $indexerFactory->create()->load($id);
        $indexer->reindexAll();
        echo "  Reindexed: {$id}\n";
    } catch (\Exception $e) {
        echo "  Skip {$id}: " . $e->getMessage() . "\n";
    }
}

echo "\nDone. Flush cache: php bin/magento cache:flush\n";
echo "Then open a category on the storefront (e.g. Default Category) to see products.\n";
