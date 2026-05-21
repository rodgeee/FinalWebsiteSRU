<?php

declare(strict_types=1);

/**
 * Builds a single src/DataFixtures/AppFixtures.php (embedded PHP data, no JSON).
 *
 * Usage:
 *   php scripts/build-app-fixtures-class.php
 *   php scripts/generate-fixture-data.php  (calls this automatically)
 */

$root = dirname(__DIR__);

function build_app_fixtures_file(string $root, string $tablesArray): void
{

$out = <<<PHP
<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ActivityLog;
use App\Entity\Adminuser;
use App\Entity\Customer;
use App\Entity\CustomerAddress;
use App\Entity\Orders;
use App\Entity\Products;
use App\Entity\Services;
use App\Entity\Staff;
use App\Entity\Stocks;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Application fixtures with embedded database snapshot.
 *
 * Regenerate: php scripts/generate-fixture-data.php
 */
final class AppFixtures extends Fixture
{
    public function load(ObjectManager \$manager): void
    {
        \$tables = self::tables();

        \$adminRefs = \$this->loadAdminusers(\$manager, \$tables['adminuser'] ?? []);
        \$staffRefs = \$this->loadStaff(\$manager, \$tables['staff'] ?? []);
        \$customerRefs = \$this->loadCustomers(\$manager, \$tables['customer'] ?? []);
        \$this->loadCustomerAddresses(\$manager, \$tables['customer_address'] ?? [], \$customerRefs);
        \$productRefs = \$this->loadProducts(\$manager, \$tables['products'] ?? [], \$staffRefs);
        \$this->loadStocks(\$manager, \$tables['stocks'] ?? [], \$productRefs, \$staffRefs);
        \$this->loadServices(\$manager, \$tables['services'] ?? [], \$staffRefs, \$customerRefs);
        \$orderRefs = \$this->loadOrders(\$manager, \$tables['orders'] ?? [], \$staffRefs);
        \$this->loadOrdersProducts(\$manager, \$tables['orders_products'] ?? [], \$orderRefs, \$productRefs);
        \$this->loadActivityLogs(\$manager, \$tables['activity_log'] ?? [], \$adminRefs);

        \$manager->flush();
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private static function tables(): array
    {
        return {$tablesArray};
    }

PHP;

    $loaderFile = $root.'/src/DataFixtures/AppFixtures.php';
    if (!is_readable($loaderFile)) {
        throw new RuntimeException('AppFixtures.php must exist before first rebuild (contains loader methods).');
    }
    $loader = (string) file_get_contents($loaderFile);
    if (!preg_match('/private function loadAdminusers.*private function jsonArray.*?\n    \}/s', $loader, $loaderMatch)) {
        throw new RuntimeException('Could not extract loader methods from AppFixtures.php');
    }

    $methods = $loaderMatch[0];
    $out .= '    '.$methods."\n}\n";

    $target = $root.'/src/DataFixtures/AppFixtures.php';
    file_put_contents($target, $out);
    echo "Written: {$target}\n";
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    $dataFile = $root.'/src/DataFixtures/AppFixtureData.php';
    if (!is_readable($dataFile)) {
        fwrite(STDERR, "Missing AppFixtureData.php — run: php scripts/generate-fixture-data.php\n");
        exit(1);
    }
    $dataSource = (string) file_get_contents($dataFile);
    if (!preg_match('/public static function tables\(\): array\s*\{\s*return\s+(.*);\s*\}\s*\}/s', $dataSource, $matches)) {
        fwrite(STDERR, "Could not extract tables array.\n");
        exit(1);
    }
    build_app_fixtures_file($root, $matches[1]);
}
