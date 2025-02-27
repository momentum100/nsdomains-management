<?php

// Enable error reporting for debugging (ELI15: This makes sure all errors are shown so we can fix them)
// ELI15: Imagine this like turning on all the warning lights on your dashboard
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bootstrap the Laravel application
require_once __DIR__ . './bootstrap/app.php';

// Autoload classes (ELI15: This tells PHP where to find the classes we need)
require_once __DIR__ . './vendor/autoload.php';

use App\Services\PricingService;

// ---------------- Helper Functions ----------------

// ELI15: A simple logging function that prefixes messages with the time and a label.
// This way we know what the program is doing at each step.
function logInfo(string $message)
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
}

// ELI15: Extract TLD from a domain by splitting on the period and returning the last part.
function extractTLD(string $domain): string
{
    $parts = explode('.', $domain);
    // Log the splitting details
    logInfo("ELI15: Splitting domain '{$domain}' into parts: " . implode(', ', $parts));
    return array_pop($parts);
}

// ---------------- Setup Dummy JSON data ----------------

// ELI15: We need to simulate the JSON file that PricingService reads so that it returns registration prices.
$jsonFilePath = __DIR__ . '/../storage/app/namecheap.json';

// Ensure the directory exists (ELI15: This creates the folder if it does not exist)
$storageDir = dirname($jsonFilePath);
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0777, true);
    logInfo("Created directory: {$storageDir}");
}

// Prepare dummy data for pricing based on the TLDs we expect
// We assume that the extracted TLD for "treetrimming.co.uk" is "uk", for "digitalhome.info" is "info", etc.
$dummyPricingData = [
    [
        "Tld" => "uk",
        "Register" => ["Price" => 3.50]
    ],
    [
        "Tld" => "info",
        "Register" => ["Price" => 3.50]
    ],
    [
        "Tld" => "creditcard",
        "Register" => ["Price" => 4.00]
    ],
    [
        "Tld" => "business",
        "Register" => ["Price" => 4.00]
    ],
    [
        "Tld" => "com",
        "Register" => ["Price" => 5.00]
    ],
    [
        "Tld" => "buzz",
        "Register" => ["Price" => 4.00]
    ],
    [
        "Tld" => "online",
        "Register" => ["Price" => 4.00]
    ],
    [
        "Tld" => "me",
        "Register" => ["Price" => 4.50]
    ],
    [
        "Tld" => "cc",
        "Register" => ["Price" => 3.75]
    ],
];

// Save dummy data to the JSON file (ELI15: We convert the data into a string and write it to a file)
file_put_contents($jsonFilePath, json_encode($dummyPricingData, JSON_PRETTY_PRINT));
logInfo("Dummy pricing data written to: {$jsonFilePath}");

// Set environment variable for DOMAIN_PRICES_JSON_PATH (ELI15: This tells our service where to find the JSON file)
putenv("DOMAIN_PRICES_JSON_PATH=storage/app/namecheap.json");

// ---------------- Prepare Test Cases ----------------

// Test cases are assembled from the table and an extra list.
// Each test case is an associative array containing the domain name, days left, and optionally some expected values.
$testCases = [
    // Table rows:
    [
        'domain' => 'treetrimming.co.uk',
        'expiration' => '2025-03-16 00:07:20',
        'registrar' => 'porkbun',
        'daysLeft' => 17,
        'expected_reg_price' => 3.50
    ],
    [
        'domain' => 'digitalhome.info',
        'expiration' => '2025-03-27 22:00:01',
        'registrar' => 'sav',
        'daysLeft' => 28,
        'expected_reg_price' => 3.50
    ],
    [
        'domain' => 'smart.creditcard',
        'expiration' => '2025-03-29 00:21:28',
        'registrar' => 'sav',
        'daysLeft' => 30,
        'expected_reg_price' => 4.00
    ],
    [
        'domain' => 'jeff.business',
        'expiration' => '2025-03-29 20:15:34',
        'registrar' => 'sav',
        'daysLeft' => 30,
        'expected_reg_price' => 4.00
    ],
    [
        'domain' => 'maral.info',
        'expiration' => '2025-03-29 22:00:01',
        'registrar' => 'sav',
        'daysLeft' => 30,
        'expected_reg_price' => 4.00
    ],
    [
        'domain' => 'elkgrovepreschool.com',
        'expiration' => '2025-03-30 12:18:20',
        'registrar' => 'spaceship',
        'daysLeft' => 31,
        'expected_reg_price' => 5.00
    ],
    [
        'domain' => 'redes.info',
        'expiration' => '2025-03-31 22:00:01',
        'registrar' => 'sav',
        'daysLeft' => 32,
        'expected_reg_price' => 4.00
    ],
    [
        'domain' => 'interchain.info',
        'expiration' => '2025-03-31 22:00:03',
        'registrar' => 'sav',
        'daysLeft' => 32,
        'expected_reg_price' => 4.00
    ],
    [
        'domain' => 'theking.info',
        'expiration' => '2025-04-01 22:00:01',
        'registrar' => 'sav',
        'daysLeft' => 33,
        'expected_reg_price' => 4.00
    ],
    [
        'domain' => 'mybear.buzz',
        'expiration' => '2025-04-03 17:30:13',
        'registrar' => 'spaceship',
        'daysLeft' => 35,
        'expected_reg_price' => 4.00
    ],
    [
        'domain' => 'newphone.buzz',
        'expiration' => '2025-04-03 17:30:13',
        'registrar' => 'spaceship',
        'daysLeft' => 35,
        'expected_reg_price' => 4.00
    ],
    [
        'domain' => 'usa1news.buzz',
        'expiration' => '2025-04-03 17:30:13',
        'registrar' => 'spaceship',
        'daysLeft' => 35,
        'expected_reg_price' => 4.00
    ],
    [
        'domain' => 'newnews.buzz',
        'expiration' => '2025-04-03 17:30:17',
        'registrar' => 'spaceship',
        'daysLeft' => 35,
        'expected_reg_price' => 4.00
    ],
    [
        'domain' => 'nameis.buzz',
        'expiration' => '2025-04-03 17:30:19',
        'registrar' => 'spaceship',
        'daysLeft' => 35,
        'expected_reg_price' => 4.00
    ],
    [
        'domain' => 'woorx.buzz',
        'expiration' => '2025-04-03 17:30:21',
        'registrar' => 'spaceship',
        'daysLeft' => 35,
        'expected_reg_price' => 4.00
    ],
    [
        'domain' => 'xoqxe.buzz',
        'expiration' => '2025-04-03 17:30:24',
        'registrar' => 'spaceship',
        'daysLeft' => 35,
        'expected_reg_price' => 4.00
    ],
    [
        'domain' => 'ertigyenix.buzz',
        'expiration' => '2025-04-03 17:30:25',
        'registrar' => 'spaceship',
        'daysLeft' => 35,
        'expected_reg_price' => 4.00
    ],
    [
        'domain' => 'hpx.info',
        'expiration' => '2025-04-03 22:00:01',
        'registrar' => 'sav',
        'daysLeft' => 35,
        'expected_reg_price' => 4.00
    ],
    [
        'domain' => 'adexqa.online',
        'expiration' => '2025-04-04 00:00:00',
        'registrar' => '123reg.co.uk',
        'daysLeft' => 36,
        'expected_reg_price' => 4.00
    ],
    [
        'domain' => 'arsena.online',
        'expiration' => '2025-04-04 00:00:00',
        'registrar' => '123reg.co.uk',
        'daysLeft' => 36,
        'expected_reg_price' => 4.00
    ],
    // Additional domains (from separate list) with assumed days left.
    [
        'domain' => 'tatra.me',
        'daysLeft' => 40,
        'expected_reg_price' => 4.50
    ],
    [
        'domain' => 'emobility.cc',
        'daysLeft' => 40,
        'expected_reg_price' => 3.75
    ],
    [
        'domain' => 'saha.cc',
        'daysLeft' => 40,
        'expected_reg_price' => 3.75
    ],
    [
        'domain' => 'webmarketing.cc',
        'daysLeft' => 40,
        'expected_reg_price' => 3.75
    ],
];

// ---------------- Run Tests ----------------

// Create an instance of the PricingService
$pricingService = new PricingService();
logInfo("PricingService instance created.");

// Initialize a counter for our tests.
$testCounter = 0;

foreach ($testCases as $case) {
    $testCounter++;
    
    // Extract the domain and TLD from the test case (ELI15: We cut the word after the last dot because that is our 'TLD')
    $domain = $case['domain'];
    $tld = extractTLD($domain);
    
    // Get the days left. If not set in this test case, assume a default (e.g. 40 days)
    $daysLeft = isset($case['daysLeft']) ? $case['daysLeft'] : 40;
    
    // Get the expected register price from our test case (if provided)
    $expectedRegisterPrice = isset($case['expected_reg_price']) ? $case['expected_reg_price'] : 'N/A';
    
    // Log the start of the test case.
    logInfo("------ Test Case #{$testCounter} ------");
    logInfo("Processing domain: {$domain} | Extracted TLD: {$tld} | Days Left: {$daysLeft}");
    
    // Get the registration price using the class method.
    $regPrice = $pricingService->getRegistrationPrice($tld);
    logInfo("Registration Price from JSON for TLD '{$tld}' = \${$regPrice}");
    
    // Also, compute the calculated final price for the domain.
    $finalPrice = $pricingService->calculatePrice($tld, $daysLeft);
    logInfo("Calculated final price for domain '{$domain}' = \${$finalPrice}");
    
    // Compare with expected registration price and output the result
    if ($regPrice == $expectedRegisterPrice) {
        logInfo("PASS: Registration price matches expected value (\${$expectedRegisterPrice}).");
    } else {
        logInfo("FAIL: Expected registration price (\${$expectedRegisterPrice}) but got \${$regPrice}.");
    }
    
    // Spacer between test cases
    logInfo("------ End of Test Case #{$testCounter} ------\n");
}

// Log final count of test cases processed.
logInfo("All tests complete. Total test cases processed: {$testCounter}.");

/*
Note: 
If you ever need to use curl in this or another script, remember to disable SSL verification like so:
   curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
This is to comply with the instruction of not verifying SSL in curl.
*/ 