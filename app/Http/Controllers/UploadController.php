<?php

// app/Http/Controllers/UploadController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Domain;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;
use League\Csv\Statement;

class UploadController extends Controller
{
    /**
     * Show the form for uploading domains.
     */
    public function showUploadForm()
    {
        return view('upload');
    }

    /**
     * Process the uploaded CSV file and add new domains.
     */
    public function uploadDomains(Request $request)
    {
        // Validate the request input
        $request->validate([
            'registrar' => 'required',
            'file' => 'required|file|mimes:csv,txt',
        ]);

        // Get the registrar and uploaded file from the request
        $registrar = $request->input('registrar');
        $file = $request->file('file');

        // Read the entire file content into an array (each line is an array element)
        $filePath = $file->getRealPath();
        $fileContent = file($filePath);

        // ELI15: Check if the first line has a specific notice text. If so, remove it.
        if (strpos($fileContent[0], 'Please note that renewal prices') !== false) {
            array_shift($fileContent);
        }

        // Convert the filtered array of file lines back into one string for CSV parsing
        $fileContentString = implode("", $fileContent);

        // Load the CSV document from the string content
        $csv = Reader::createFromString($fileContentString);

        // Set CSV delimiter conditionally based on the registrar input value.
        if ($registrar === 'regery') {
            $csv->setDelimiter(';');
        }

        $csv->setHeaderOffset(0); // Tells the library that the first row is the header

        // Log the CSV header info for debugging
        $header = $csv->getHeader();
        Log::info('CSV Header: ' . implode(', ', $header));

        // Create a statement object to process the CSV content
        $stmt = (new Statement());

        // Process the records from the CSV
        $records = $stmt->process($csv);

        // Log how many rows were initially found in the CSV
        Log::info('Initial data count: ' . count($records));

        $newDomainsCount = 0; // Counter for new domains added

        // Process each row from the CSV data
        foreach ($records as $index => $row) {
            // Log a counter for each row being processed
            Log::info("Processing row " . ($index + 1));

            // Parse the row based on registrar and extract domain details
            $domainData = $this->parseRow($registrar, $row);

            // If parsing fails, log a warning and skip this row.
            if (!$domainData) {
                Log::warning('Skipping row due to parsing error: ' . implode(', ', $row));
                continue;
            }

            // Check if the domain already exists in the database.
            if (Domain::where('domain', $domainData['domain'])->exists()) {
                Log::info('Domain already exists: ' . $domainData['domain']);
                continue;
            }

            // Create the new domain record in the database.
            Domain::create($domainData);
            $newDomainsCount++; // Increase our counter for each new domain added
            Log::info('New domain added: ' . $domainData['domain']);
        }

        // Log the total number of newly added domains.
        Log::info('Total new domains added: ' . $newDomainsCount);

        // Redirect back with a success message
        return redirect()->back()->with('success', "Domains uploaded successfully. New domains added: $newDomainsCount. Go <a href='/domains'>here</a> to view them.");
    }

    /**
     * Parse a CSV row based on the registrar type.
     * ELI15: This function picks the right CSV columns based on the registrar to get the domain and the expiration date.
     */
    private function parseRow($registrar, $row)
    {
        switch ($registrar) {
            case 'cosmotown':
                $domain = $row['Domain Name'];
                $expDate = $row['Expiry Date'];
                $expTimestamp = strtotime($expDate);
                break;
            case 'dynadot':
                $domain = $row['Domain'];
                $expDate = $row['Expiration Date'];
                $expTimestamp = $this->parseDynadotDate($expDate);
                break;
            case 'spaceship':
                $domain = $row['Domain'];
                $expDate = $row['Expiration Date'];
                $expTimestamp = strtotime($expDate);
                break;
            case 'namecheap':
                $domain = $row['Domain Name'];
                $expDate = $row['Domain expiration date'];
                $expTimestamp = strtotime($expDate);
                break;
            case 'porkbun':
                $domain = $row['DOMAIN'];
                $expDate = $row['EXPIRE DATE'];
                $expTimestamp = strtotime($expDate);
                break;
            case 'namebright':
                $domain = $row['Domain'];
                $expDate = $row['Expiration Date'];
                $expTimestamp = strtotime($expDate);
                break;
            case 'godaddy':
                $domain = $row['Domain Name'];
                $expDate = $row['Expiration Date'];
                $expTimestamp = strtotime($expDate);
                break;
            case 'sav':
                $domain = $row['domain_name'];
                $expDate = $row['date_expiration'];
                $expTimestamp = strtotime($expDate);
                break;
            case 'regery':
                $domain = $row['Domain name'];
                $expDate = $row['Exp Date'];
                $expTimestamp = strtotime($expDate);
                break;
            case 'namecom': // Handle namecom with updated fields
                $domain = $row['Domain Name'] ?? null;
                $expDate = $row['Expire Date'] ?? null;
                $expTimestamp = $expDate ? strtotime($expDate) : null;
                break;
            case '123reg.co.uk':
                // ELI15: For 123reg.co.uk, we use the same column names as shown in the CSV sample.
                $domain = $row['Domain Name'];
                $expDate = $row['Expiration Date'];
                $expTimestamp = strtotime($expDate);
                break;
            default:
                // If registrar doesn't match any case, return null.
                return null;
        }

        // ELI15: Make sure we have a domain and a valid expiration date.
        if (empty($domain)) {
            Log::warning('Parsing error: Domain is missing', ['row' => $row]);
            return null;
        }

        if (empty($expTimestamp)) {
            Log::warning('Parsing error: Expiration date is invalid or missing', ['row' => $row]);
            return null;
        }

        // Return the domain data as an array.
        return [
            'domain'    => $domain,
            'exp_date'  => $expTimestamp,
            'registrar' => $registrar,
        ];
    }

    /**
     * Parse date string for Dynadot registrar.
     * ELI15: This function helps us adjust Dynadot's date format to a standard timestamp.
     */
    private function parseDynadotDate($dateString)
    {
        // Remove the timezone abbreviation if present and add our own timezone.
        $dateString = str_replace(' PST', '', $dateString);
        return strtotime($dateString . ' America/Los_Angeles');
    }
}
