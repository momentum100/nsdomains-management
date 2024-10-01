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
    public function showUploadForm()
    {
        return view('upload');
    }

    public function uploadDomains(Request $request)
    {
        $request->validate([
            'registrar' => 'required',
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $registrar = $request->input('registrar');
        $file = $request->file('file');

        // Read the file content
        $filePath = $file->getRealPath();
        $fileContent = file($filePath);

        // Check and strip the first line if it contains the specific text
        if (strpos($fileContent[0], 'Please note that renewal prices') !== false) {
            array_shift($fileContent);
        }

        // Convert the modified content back to a string
        $fileContentString = implode("", $fileContent);

        // Load the CSV document from the string
        $csv = Reader::createFromString($fileContentString);

        // Set the delimiter conditionally based on the registrar
        if ($registrar === 'regery') {
            $csv->setDelimiter(';');
        }

        $csv->setHeaderOffset(0); // Set the header offset

        // Get the header
        $header = $csv->getHeader();
        Log::info('CSV Header: ' . implode(', ', $header));

        // Create a statement to process the CSV
        $stmt = (new Statement());

        // Process the CSV records
        $records = $stmt->process($csv);

        // Log the initial data count
        Log::info('Initial data count: ' . count($records));

        $newDomainsCount = 0; // Initialize counter for new domains

        foreach ($records as $row) {
            $domainData = $this->parseRow($registrar, $row);

            if (!$domainData) {
                Log::warning('Skipping row due to parsing error: ' . implode(', ', $row));
                continue;
            }

            if (Domain::where('domain', $domainData['domain'])->exists()) {
                Log::info('Domain already exists: ' . $domainData['domain']);
                continue;
            }

            Domain::create($domainData);
            $newDomainsCount++; // Increment counter for each new domain added
            Log::info('New domain added: ' . $domainData['domain']);
        }

        Log::info('Total new domains added: ' . $newDomainsCount);
        return redirect()->back()->with('success', "Domains uploaded successfully. New domains added: $newDomainsCount. Go <a href='/domains'>here</a> to view them.");
    }

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
            case 'namecom': // Updated to match the input registrar
                $domain = $row['Domain Name'] ?? null;
                $expDate = $row['Expire Date'] ?? null;
                $expTimestamp = $expDate ? strtotime($expDate) : null;
                break;
            default:
                return null;
        }

        // Ensure the domain and exp_date are set correctly
        if (empty($domain)) {
            Log::warning('Parsing error: Domain is missing', ['row' => $row]);
            return null;
        }

        if (empty($expTimestamp)) {
            Log::warning('Parsing error: Expiration date is invalid or missing', ['row' => $row]);
            return null;
        }

        return [
            'domain' => $domain,
            'exp_date' => $expTimestamp,
            'registrar' => $registrar,
        ];
    }

    private function parseDynadotDate($dateString)
    {
        $dateString = str_replace(' PST', '', $dateString);
        return strtotime($dateString . ' America/Los_Angeles');
    }
}
