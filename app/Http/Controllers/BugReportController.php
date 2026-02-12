<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class BugReportController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'message' => 'required|string|min:5',
                'screenshot' => 'nullable|array',
                'screenshot.*' => 'nullable|file|max:5120',
                'website' => ['nullable','string','max:0'],
            ]);

            $email = env('BUG_REPORT_EMAIL', 'brunokristian003@gmail.com');
            
            // Send email without attachments first
            $emailBody = "New Bug Report\n\n" .
                "From: {$validated['email']}\n\n" .
                "Message:\n{$validated['message']}";
            
            Mail::raw($emailBody, function ($message) use ($email, $validated, $request) {
                $message->to($email)
                    ->subject('New Bug Report from ' . $validated['email']);

                if ($request->hasFile('screenshot')) {
                    try {
                        foreach ($request->file('screenshot') as $file) {
                            $message->attach($file->getRealPath(), [
                                'as' => $file->getClientOriginalName(),
                                'mime' => $file->getMimeType(),
                            ]);
                        }
                    } catch (\Exception $e) {
                        // Continue even if attachments fail
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Bug report sent successfully'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . implode(', ', array_values($e->errors())[0] ?? [])
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Bug Report Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error sending bug report'
            ], 500);
        }
    }
}
