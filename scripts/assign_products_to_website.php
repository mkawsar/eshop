#!/usr/bin/env php
<?php
/**
 * Assign all existing products to the default website (base) so they show on the storefront.
 * Run from project root: php scripts/assign_products_to_website.php
 * Or in Docker: docker compose exec app php scripts/assign_products_to_website.php
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

$websiteId = 1; // default "base" website
$productRepository = $obj->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
$websiteRepository = $obj->get(\Magento\Store\Api\WebsiteRepositoryInterface::class);

try {
    $website = $websiteRepository->get('base');
    $websiteId = (int) $website->getId();
} catch (\Exception $e) {
    echo "Using website_id=1 (base). If your default website has another ID, edit this script.\n";
}

$resource = $obj->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resource->getConnection();
$productTable = $resource->getTableName('catalog_product_entity');
$websiteTable = $resource->getTableName('catalog_product_website');

$select = $connection->select()
    ->from($productTable, ['entity_id'])
    ->where('type_id = ?', 'simple');
$productIds = $connection->fetchCol($select);

$existing = $connection->fetchPairs(
    $connection->select()
        ->from($websiteTable, ['product_id' => 'product_id'])
        ->where('website_id = ?', $websiteId)
);
$existing = array_flip($existing);

$toAdd = array_filter($productIds, function ($id) use ($existing) {
    return !isset($existing[$id]);
});

if (empty($toAdd)) {
    echo "All products are already assigned to website_id={$websiteId}. Nothing to do.\n";
    exit(0);
}

foreach (array_chunk($toAdd, 500) as $chunk) {
    foreach ($chunk as $productId) {
        $connection->insertOnDuplicate($websiteTable, [
            'product_id' => $productId,
            'website_id' => $websiteId,
        ]);
    }
}

echo "Assigned " . count($toAdd) . " product(s) to website_id={$websiteId} (base).\n";
echo "Run reindex and flush cache:\n";
echo "  php bin/magento indexer:reindex\n";
echo "  php bin/magento cache:flush\n";
