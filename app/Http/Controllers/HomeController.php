<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard based on user role.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        // Log dashboard access
        Log::info('Dashboard access', [
            'user_id' => auth()->id(),
            'is_admin' => auth()->user()->is_admin
        ]);

        // Redirect admin users to domains page
        if (auth()->user()->is_admin) {
            return redirect('/domains');
        }

        // Show regular user dashboard
        return view('dashboard.user');
    }
}
