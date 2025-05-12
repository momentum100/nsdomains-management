<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminCryptoPaymentController extends Controller
{
    // ELI15: Show the form for admin to enter transaction hash and receiver address
    public function showForm(Request $request)
    {
        // Get previous result and counter from session (if any)
        $result = session('crypto_check_result');
        $log = session('crypto_check_log');
        $counter = session('crypto_check_counter', 0);
        return view('admin.crypto-payment-check', compact('result', 'log', 'counter'));
    }

    // ELI15: Handle the form, call the API, log everything, and show prettified JSON
    public function checkTransaction(Request $request)
    {
        $request->validate([
            'transaction_hash' => 'required|string',
            'receiver_address' => 'nullable|string',
        ]);

        $hash = $request->input('transaction_hash');
        $receiver = $request->input('receiver_address');
        $apiKey = 'test_api_key_123'; // ELI15: Use your API key here
        $url = 'https://crypto-payments.cheaptools.club/check-transaction';

        $payload = [
            'transaction_hash' => $hash,
        ];
        if ($receiver) {
            $payload['receiver_address'] = $receiver;
        }

        $jsonPayload = json_encode($payload);

        // ELI15: Set up cURL to call the API
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-API-KEY: ' . $apiKey,
            ],
            CURLOPT_SSL_VERIFYPEER => false, // ELI15: Don't verify SSL (for dev/testing)
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        // ELI15: Log the request and response
        $log = [
            'request' => $jsonPayload,
            'response' => $response,
            'error' => $error,
        ];
        Log::info('AdminCryptoPaymentCheck', $log);

        // ELI15: Prettify JSON for output
        $pretty = $response ? json_encode(json_decode($response), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 'No response or invalid JSON.';
        if ($error) {
            $pretty .= "\nCURL ERROR: $error";
        }

        // ELI15: Count how many checks in this session
        $counter = session('crypto_check_counter', 0) + 1;
        session([
            'crypto_check_result' => $pretty,
            'crypto_check_log' => $log,
            'crypto_check_counter' => $counter,
        ]);

        return redirect()->route('admin.crypto-payment-check')->withInput();
    }
} 