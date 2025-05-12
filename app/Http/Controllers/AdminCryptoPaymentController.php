<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class AdminCryptoPaymentController extends Controller
{
    // ELI15: Show the form for admin to enter transaction hash and receiver address
    public function showForm(Request $request)
    {
        // Get previous result and counter from session (if any)
        $result = session('crypto_check_result');
        $log = session('crypto_check_log');
        $counter = session('crypto_check_counter', 0);
        $humanSummary = session('crypto_check_human_summary');
        return view('admin.crypto-payment-check', compact('result', 'log', 'counter', 'humanSummary'));
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

        // ELI15: Human summary logic
        $humanSummary = '';
        $data = json_decode($response, true);
        $myWallets = Config::get('mywallets');
        $emoji = '';
        $matchedWallet = null;
        if (isset($data['data']['blockchain'])) {
            $blockchain = $data['data']['blockchain'];
            $wallets = $myWallets[$blockchain] ?? [];
            if ($blockchain === 'BITCOIN') {
                // Look for the special address in all_outputs
                $target = $wallets[0] ?? 'bc1q26e3zj4yt7u8agju2ajy0z9ljxvhgzlppjgavh';
                $found = null;
                if (isset($data['data']['all_outputs']) && is_array($data['data']['all_outputs'])) {
                    foreach ($data['data']['all_outputs'] as $output) {
                        if (isset($output['address']) && in_array($output['address'], $wallets)) {
                            $found = $output;
                            $matchedWallet = $output['address'];
                            $emoji = '✅';
                            break;
                        }
                    }
                }
                if ($found) {
                    $humanSummary = "To <b>{$found['address']}</b>: <b>{$found['amount_btc']} BTC</b> ({$found['amount_usd']} USD) $emoji<br>Token: <b>Bitcoin (BTC)</b>";
                } else {
                    $humanSummary = "No output found for your wallets in this transaction.";
                }
            } else {
                // Other blockchains: from → to, token, amount
                $from = $data['data']['from_address'] ?? '';
                $to = $data['data']['to_address'] ?? '';
                // Check if to_address is one of my wallets
                if (in_array($to, $wallets)) {
                    $emoji = '✅';
                    $matchedWallet = $to;
                }
                $tokenName = $data['data']['token_name'] ?? '';
                $tokenSymbol = $data['data']['token_symbol'] ?? '';
                $amount = $data['data']['amount_transferred'] ?? '';
                $humanSummary = "<b>$from</b> → <b>$to</b> $emoji<br>Token: <b>$tokenName ($tokenSymbol)</b><br>Amount: <b>$amount</b>";
            }
        } else {
            $humanSummary = 'No blockchain data found.';
        }
        Log::info('AdminCryptoPaymentCheckHumanSummary', ['summary' => $humanSummary, 'matched_wallet' => $matchedWallet]);

        // ELI15: Count how many checks in this session
        $counter = session('crypto_check_counter', 0) + 1;
        session([
            'crypto_check_result' => $pretty,
            'crypto_check_log' => $log,
            'crypto_check_counter' => $counter,
            'crypto_check_human_summary' => $humanSummary,
        ]);

        return redirect()->route('admin.crypto-payment-check')->withInput();
    }
} 