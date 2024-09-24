<?php

// app/Http/Controllers/UploadController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Domain;

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

        // Skip the first line for porkbun before we get header
        if ($registrar === 'porkbun') {
            array_shift($data);
        }
        $header = array_shift($data);
    
        $newDomainsCount = 0; // Initialize counter for new domains
    
        foreach ($data as $row) {
            $row = array_combine($header, $row);
            $domainData = $this->parseRow($registrar, $row);
    
            if (!$domainData) {
                continue;
            }
    
            if (Domain::where('domain', $domainData['domain'])->exists()) {
                continue;
            }
    
            Domain::create($domainData);
            $newDomainsCount++; // Increment counter for each new domain added
        }
    
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
