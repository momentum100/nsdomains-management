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
        \Log::info('Total domains: ' . $total);

        return view('domains.index', compact('domains', 'total', 'status')); // Pass status to view
    }

    public function destroy($id)
    {
        $domain = Domain::findOrFail($id);
        $domain->status = 'SOLD'; // Change status to SOLD
        $domain->save();

        return redirect()->route('domains.index')->with('success', 'Domain marked as sold successfully');
    }
}