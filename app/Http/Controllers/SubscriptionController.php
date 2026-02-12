<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required','email','max:255'],
            'preference' => ['required','in:notify,test'],
            'website' => ['nullable','max:0'],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Neplatný email alebo preferencia.'], 422);
        }

        try {
            // Send email notification
            Mail::raw(
                "Nový záujemca o aplikáciu ADOcare\n\n" .
                "Email: {$request->email}\n" .
                "Preferencia: {$request->preference}\n",
                function ($message) use ($request) {
                    $message->to('brunokristian003@gmail.com')
                        ->subject('Nový záujemca - ADOcare');
                }
            );

            return response()->json([
                'message' => 'Registrácia bola úspešná!',
                'email' => $request->email,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Chyba pri odoslaní emailu.'], 500);
        }
    }
}
