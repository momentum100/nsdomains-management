<?php

// app/Http/Controllers/DomainController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Domain;
use Illuminate\Support\Facades\DB;

class DomainController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'ACTIVE'); // Get status from query, default to ACTIVE
        $domains = Domain::select('*', DB::raw('DATEDIFF(FROM_UNIXTIME(exp_date), NOW()) as days_left'))
                         ->where('status', $status) // Filter by status
                         ->orderBy('exp_date')
                         ->get();
        $total = $domains->count();
        $active = Domain::where('status', 'ACTIVE')->count();
        $sold = Domain::where('status', 'SOLD')->count();
        \Log::info('Total domains: ' . $total);

        return view('domains.index', compact('domains', 'total', 'status', 'active', 'sold')); // Pass status and counts to view
    }

    public function exportCsv()
    {
        $domains = Domain::where('status', 'ACTIVE') // Filter by ACTIVE status
                         ->orderBy('exp_date')
                         ->get();
        $filename = "domains_" . date('Ymd_His') . ".csv";
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
        ];
        $columns = ['Domain', 'Expiration Date', 'Registrar'];

        $callback = function() use ($domains, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            foreach ($domains as $domain) {
                fputcsv($file, [
                    $domain->domain,
                    date('Y-m-d H:i:s', $domain->exp_date),
                    $domain->registrar,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function destroy(Request $request)
    {
        \Log::info('Bulk mark as sold request received', $request->all());
        $domainIds = $request->input('domains');
        if ($domainIds) {
            Domain::whereIn('id', $domainIds)
                ->update([
                    'status' => 'SOLD',
                    'updated_at' => now()
                ]);
        }
        return redirect()->route('domains.index')->with('success', 'Selected domains marked as sold successfully.');
    }
    public function bulkDestroy(Request $request)
    {
        \Log::info('Bulk mark as sold request received', $request->all());
        $domainIds = $request->input('domains');
        if ($domainIds) {
            Domain::whereIn('id', $domainIds)
                ->update([
                    'status' => 'SOLD',
                    'updated_at' => now()
                ]);
        }
        return redirect()->route('domains.index')->with('success', 'Selected domains marked as sold successfully.');
    }
}
