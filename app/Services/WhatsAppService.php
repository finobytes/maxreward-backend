<?php

namespace App\Services;

use App\Models\WhatsAppMessageLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class WhatsAppService
{
    // protected $apiUrl;
    // protected $apiKey;
    // protected $apiSecret;

    // public function __construct()
    // {
    //     // Configure your WhatsApp API credentials
    //     // You can use Twilio, WhatsApp Business API, or any other provider
    //     $this->apiUrl = env('WHATSAPP_API_URL', 'https://api.whatsapp.com');
    //     $this->apiKey = env('WHATSAPP_API_KEY', '');
    //     $this->apiSecret = env('WHATSAPP_API_SECRET', '');
    // }

    /**
     * Send welcome message to new member
     * 
     * @param array $data Member and login data
     * @return bool Success status
     */
    public function sendWelcomeMessage($data)
    {
        $message = $this->formatWelcomeMessage($data);
        
        $logEntry = WhatsAppMessageLog::logMessage([
            'member_id' => $data['member_id'],
            'sent_by_member_id' => $data['referrer_id'] ?? null,
            'phone_number' => $data['phone'],
            'message_type' => 'referral_invite',
            'message_content' => $message,
            'status' => 'pending',
        ]);

        try {
            // Send via WhatsApp API
            $response = $this->sendMessage($data['phone'], $message);
            
            if ($response['success']) {
                $logEntry->markAsSent();
                return true;
            } else {
                $logEntry->markAsFailed($response['error'] ?? 'Unknown error');
                return false;
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp send failed: ' . $e->getMessage());
            $logEntry->markAsFailed($e->getMessage());
            return false;
        }
    }

    /**
     * Format welcome message template
     */
    private function formatWelcomeMessage($data)
    {
        return "🎉 Welcome to MaxReward!\n\n" .
               "Hello {$data['name']}!\n\n" .
               "Your account has been created successfully.\n\n" .
               "📱 Login Details:\n" .
               "Username: {$data['user_name']}\n" .
               "Password: {$data['password']}\n" .
               "Login Link: {$data['login_url']}\n\n" .
               "Start earning rewards today! 🚀\n\n" .
               "Need help? Contact support.";
    }

    /**
     * Send message via WhatsApp API
     * 
     * @param string $phone Phone number with country code
     * @param string $message Message content
     * @return array Response
     */
    private function sendMessage($phone, $message)
    {
        // // IMPLEMENTATION DEPENDS ON YOUR WHATSAPP PROVIDER
        
        // // Example for Twilio:
        // /*
        // $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
        //     ->post($this->apiUrl . '/Messages', [
        //         'From' => 'whatsapp:+' . env('WHATSAPP_FROM_NUMBER'),
        //         'To' => 'whatsapp:+' . $phone,
        //         'Body' => $message
        //     ]);

        // return [
        //     'success' => $response->successful(),
        //     'error' => $response->failed() ? $response->body() : null
        // ];
        // */

        // // For now, simulate success (REPLACE WITH REAL API INTEGRATION)
        // Log::info("WhatsApp Message to {$phone}: {$message}");
        
        // return [
        //     'success' => true,
        //     'error' => null
        // ];

        // try {
        //     $sid    = env('TWILIO_SID');
        //     $token  = env('TWILIO_AUTH_TOKEN');
        //     $from   = env('TWILIO_WHATSAPP_FROM'); // e.g. whatsapp:+14155238886
        //     $contentSid = env('TWILIO_CONTENT_SID'); // optional
        //     $contentVars = json_encode([
        //         "1" => "12/1",
        //         "2" => "3pm"
        //     ]); // optional example variables

        //     $twilio = new Client($sid, $token);

        //     $params = [
        //         "from" => $from,
        //         "body" => $message, // basic message
        //         // Uncomment below if using Twilio content templates:
        //         // "contentSid" => $contentSid,
        //         // "contentVariables" => $contentVars,
        //     ];

        //     $twilio->messages->create("whatsapp:+{$phone}", $params);

            Log::info("✅ WhatsApp message sent successfully to {$phone}: {$message}");

            return [
                'success' => true,
                'error' => null,
            ];
    
        } catch (\Exception $e) {
            Log::error("❌ WhatsApp send failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}