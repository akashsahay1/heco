<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\WelcomeEmail;
use App\Mail\PasswordResetEmail;
use App\Mail\BookingConfirmationEmail;
use App\Mail\PaymentReceivedEmail;
use App\Mail\SpApplicationReceivedEmail;
use App\Mail\ProfileUpdatedEmail;
use App\Mail\PasswordChangedEmail;

class EmailTestController extends Controller
{
    public function show()
    {
        return view('admin.email-test');
    }

    public function send(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $to = $request->input('email');

        $sampleTrip = [
            'traveller_name' => 'Test Traveller',
            'trip_id' => 'HECO-TEST-001',
            'trip_name' => 'Spiti Valley Discovery',
            'start_date' => now()->addDays(30)->format('d M Y'),
            'end_date' => now()->addDays(37)->format('d M Y'),
            'adults' => 2,
            'children' => 0,
            'total_cost' => 84500,
        ];

        $samplePayment = [
            'traveller_name' => 'Test Traveller',
            'amount' => 25000,
            'payment_date' => now()->format('d M Y'),
            'trip_id' => 'HECO-TEST-001',
            'reference' => 'pay_test_' . strtoupper(bin2hex(random_bytes(6))),
        ];

        $emails = [
            'welcome' => new WelcomeEmail('Test Traveller', url('/login')),
            'password_reset' => new PasswordResetEmail('Test Traveller', url('/reset-password/sample-token-' . bin2hex(random_bytes(8)))),
            'booking_confirmation' => new BookingConfirmationEmail($sampleTrip),
            'payment_received' => new PaymentReceivedEmail($samplePayment),
            'sp_application_received' => new SpApplicationReceivedEmail('Test Partner', 'Homestay Local Host (HLH)'),
            'profile_updated' => new ProfileUpdatedEmail(
                'Test Traveller',
                ['Full name' => 'Test Traveller', 'Mobile' => '+91 98765 43210'],
                now()->format('d M Y, h:i A')
            ),
            'password_changed' => new PasswordChangedEmail('Test Traveller', now()->format('d M Y, h:i A')),
        ];

        $results = [];
        foreach ($emails as $key => $mailable) {
            try {
                Mail::to($to)->send($mailable);
                $results[$key] = ['status' => 'sent', 'subject' => $mailable->envelope()->subject];
            } catch (\Throwable $e) {
                Log::error("Email test failed [{$key}]: " . $e->getMessage());
                $results[$key] = ['status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => true,
            'to' => $to,
            'mailer' => config('mail.default'),
            'results' => $results,
        ]);
    }
}
