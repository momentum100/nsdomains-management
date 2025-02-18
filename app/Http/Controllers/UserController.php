<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function dashboard()
    {
        $user = Auth::user();
        return view('dashboard.user', compact('user'));
    }

    public function showPaymentDetails()
    {
        $user = Auth::user();
        return view('users.payment-details', compact('user'));
    }

    public function updatePaymentDetails(Request $request)
    {
        $request->validate([
            'payment_details' => 'required|string|max:1000'
        ]);

        $user = Auth::user();
        $user->payment_details = $request->payment_details;
        $user->save();

        return redirect()->back()->with('status', 'Payment details updated successfully!');
    }
} 