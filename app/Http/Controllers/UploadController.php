<?php

// app/Http/Controllers/UploadController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Domain;
use Illuminate\Support\Facades\Log;

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
    
        $data = array_map('str_getcsv', file($file->getRealPath()));

        // Log the initial data count
        Log::info('Initial data count: ' . count($data));

        // Skip lines containing "Please note that renewal prices" for porkbun
        if ($registrar === 'porkbun') {
            $data = array_filter($data, function($line) {
                return strpos($line[0], 'Please note that renewal prices') === false;
            });
            // Log the data count after filtering
            Log::info('Data count after filtering for porkbun: ' . count($data));
        }

        $header = array_shift($data);
        // Log the header
        Log::info('CSV Header: ' . implode(', ', $header));
    
        $newDomainsCount = 0; // Initialize counter for new domains
    
        foreach ($data as $row) {
            $row = array_combine($header, $row);
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
            default:
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
