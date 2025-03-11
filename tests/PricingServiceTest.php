<?php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Autoload classes first
require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap the Laravel application
$app = require_once __DIR__ . '/../bootstrap/app.php';

// Bootstrap the application kernel so that facades are set up
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\PricingService;

// ---------------- Helper Functions ----------------

function logInfo(string $message)
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
}

function extractTLD(string $domain): string
{
    $parts = explode('.', $domain);
    logInfo("ELI15: Splitting domain '{$domain}' into parts: " . implode(', ', $parts));
    return array_pop($parts);
}

// ---------------- Setup Dummy JSON data ----------------

$jsonFilePath = __DIR__ . '/../storage/app/namecheap.json';

$storageDir = dirname($jsonFilePath);
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0777, true);
    logInfo("Created directory: {$storageDir}");
}

$dummyPricingData = [
    ["Tld" => "uk", "Register" => ["Price" => 3.50]],
    ["Tld" => "info", "Register" => ["Price" => 3.50]],
    ["Tld" => "creditcard", "Register" => ["Price" => 4.00]],
    ["Tld" => "business", "Register" => ["Price" => 4.00]],
    ["Tld" => "com", "Register" => ["Price" => 10.00]],
    ["Tld" => "buzz", "Register" => ["Price" => 4.00]],
    ["Tld" => "online", "Register" => ["Price" => 4.00]],
    ["Tld" => "me", "Register" => ["Price" => 4.50]],
    ["Tld" => "cc", "Register" => ["Price" => 3.75]],
];

file_put_contents($jsonFilePath, json_encode($dummyPricingData, JSON_PRETTY_PRINT));
logInfo("Dummy pricing data written to: {$jsonFilePath}");

putenv("DOMAIN_PRICES_JSON_PATH=storage/app/namecheap.json");

// ---------------- Prepare Test Cases ----------------

$testCases = [
    ['domain' => 'treetrimming.co.uk', 'daysLeft' => 17, 'expected_reg_price' => 3.50],
    ['domain' => 'digitalhome.info', 'daysLeft' => 28, 'expected_reg_price' => 3.50],
    ['domain' => 'smart.creditcard', 'daysLeft' => 30, 'expected_reg_price' => 4.00],
    ['domain' => 'jeff.business', 'daysLeft' => 30, 'expected_reg_price' => 4.00],
    ['domain' => 'maral.info', 'daysLeft' => 30, 'expected_reg_price' => 4.00],
    ['domain' => 'elkgrovepreschool.com', 'daysLeft' => 31, 'expected_reg_price' => 5.00],
    ['domain' => 'redes.info', 'daysLeft' => 32, 'expected_reg_price' => 4.00],
    ['domain' => 'interchain.info', 'daysLeft' => 32, 'expected_reg_price' => 4.00],
    ['domain' => 'theking.info', 'daysLeft' => 33, 'expected_reg_price' => 4.00],
    ['domain' => 'mybear.buzz', 'daysLeft' => 35, 'expected_reg_price' => 4.00],
    ['domain' => 'newphone.buzz', 'daysLeft' => 35, 'expected_reg_price' => 4.00],
    ['domain' => 'usa1news.buzz', 'daysLeft' => 35, 'expected_reg_price' => 4.00],
    ['domain' => 'newnews.buzz', 'daysLeft' => 35, 'expected_reg_price' => 4.00],
    ['domain' => 'nameis.buzz', 'daysLeft' => 35, 'expected_reg_price' => 4.00],
    ['domain' => 'woorx.buzz', 'daysLeft' => 35, 'expected_reg_price' => 4.00],
    ['domain' => 'xoqxe.buzz', 'daysLeft' => 35, 'expected_reg_price' => 4.00],
    ['domain' => 'ertigyenix.buzz', 'daysLeft' => 35, 'expected_reg_price' => 4.00],
    ['domain' => 'hpx.info', 'daysLeft' => 35, 'expected_reg_price' => 4.00],
    ['domain' => 'adexqa.online', 'daysLeft' => 36, 'expected_reg_price' => 4.00],
    ['domain' => 'arsena.online', 'daysLeft' => 36, 'expected_reg_price' => 4.00],
    ['domain' => 'tatra.me', 'daysLeft' => 40, 'expected_reg_price' => 4.50],
    ['domain' => 'emobility.cc', 'daysLeft' => 40, 'expected_reg_price' => 3.75],
    ['domain' => 'saha.cc', 'daysLeft' => 40, 'expected_reg_price' => 3.75],
    ['domain' => 'webmarketing.cc', 'daysLeft' => 40, 'expected_reg_price' => 3.75],
];

// ---------------- Run Tests ----------------

$pricingService = new PricingService();
logInfo("PricingService instance created.");

$testCounter = 0;

foreach ($testCases as $case) {
    $testCounter++;
    
    $domain = $case['domain'];
    $tld = extractTLD($domain);
    $daysLeft = $case['daysLeft'];
    $expectedRegisterPrice = $case['expected_reg_price'];
    
    logInfo("------ Test Case #{$testCounter} ------");
    logInfo("Processing domain: {$domain} | Extracted TLD: {$tld} | Days Left: {$daysLeft}");
    
    $regPrice = $pricingService->getRegistrationPrice($tld);
    logInfo("Registration Price from JSON for TLD '{$tld}' = \${$regPrice}");
    
    $finalPrice = $pricingService->calculatePrice($tld, $daysLeft);
    logInfo("Calculated final price for domain '{$domain}' = \${$finalPrice}");
    
    if ($regPrice == $expectedRegisterPrice) {
        logInfo("PASS: Registration price matches expected value (\${$expectedRegisterPrice}).");
    } else {
        logInfo("FAIL: Expected registration price (\${$expectedRegisterPrice}) but got \${$regPrice}.");
    }
    
    logInfo("------ End of Test Case #{$testCounter} ------\n");
}

logInfo("All tests complete. Total test cases processed: {$testCounter}.");

/*
Note: 
If you ever need to use curl in this or another script, remember to disable SSL verification like so:
   curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
This is to comply with the instruction of not verifying SSL in curl.
*/