<?php

namespace App\Http\Controllers;

use App\Mail\GenericEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MailTestController extends Controller
{
    public function send(Request $request)
    {
        $to = $request->input('to', config('mail.from.address'));
        $recipientName = $request->input('name', 'Test User');

        $data = [
            'recipientName' => $recipientName,
            'body' => 'This is a test email sent using the generic blade email template.',
            'headerTitle' => config('app.name'),
            'footerText' => 'If you did not request this, please ignore.'
        ];

        Mail::to($to)->send(new GenericEmail('Test: Generic Email', $data));

        return response()->json(['status' => 'sent', 'to' => $to]);
    }
}
