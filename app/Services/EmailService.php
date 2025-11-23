<?php

namespace App\Services;

use App\Models\EmailMessageLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailService
{
    /**
     * Send welcome email to new member
     *
     * @param array $data Member and login data
     * @return bool Success status
     */
    public function sendWelcomeEmail($data)
    {
        $message = $this->formatWelcomeMessage($data);

        $logEntry = EmailMessageLog::logMessage([
            'member_id' => $data['member_id'],
            'sent_by_member_id' => $data['referrer_id'] ?? null,
            'email_address' => $data['email'],
            'message_type' => 'referral_invite',
            'message_content' => $message,
            'status' => 'pending',
        ]);

        try {
            // Send via Laravel Mail
            $response = $this->sendEmail($data['email'], $data['name'], $message, $data);

            if ($response['success']) {
                $logEntry->markAsSent();
                return true;
            } else {
                $logEntry->markAsFailed($response['error'] ?? 'Unknown error');
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Email send failed: ' . $e->getMessage());
            $logEntry->markAsFailed($e->getMessage());
            return false;
        }
    }

    /**
     * Format welcome message template
     */
    private function formatWelcomeMessage($data)
    {
        return "Welcome to MaxReward!\n\n" .
               "Hello {$data['name']}!\n\n" .
               "Your account has been created successfully.\n\n" .
               "Login Details:\n" .
               "Username: {$data['user_name']}\n" .
               "Password: {$data['password']}\n" .
               "Login Link: {$data['login_url']}\n\n" .
               "Start earning rewards today! \n\n" .
               "Need help? Contact support.";
    }

    /**
     * Send email via Laravel Mail
     *
     * @param string $email Email address
     * @param string $name Recipient name
     * @param string $message Message content
     * @param array $data Additional data for email template
     * @return array Response
     */
    private function sendEmail($email, $name, $message, $data)
    {
        try {
            Mail::send([], [], function ($mail) use ($email, $name, $message, $data) {
                $mail->to($email, $name)
                     ->subject('Welcome to MaxReward - Your Account Details')
                     ->html($this->formatHtmlEmail($data));
            });

            Log::info("Email sent successfully to {$email}");

            return [
                'success' => true,
                'error' => null,
            ];

        } catch (\Exception $e) {
            Log::log("Email send failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Format HTML email template
     */
    private function formatHtmlEmail($data)
    {
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; }
                .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
                .content { background-color: white; padding: 30px; margin-top: 20px; border-radius: 5px; }
                .credentials { background-color: #f0f0f0; padding: 15px; border-left: 4px solid #4CAF50; margin: 20px 0; }
                .button { display: inline-block; padding: 12px 30px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Welcome to MaxReward!</h1>
                </div>
                <div class='content'>
                    <h2>Hello {$data['name']}!</h2>
                    <p>Your account has been created successfully. We're excited to have you join our MaxReward community!</p>

                    <div class='credentials'>
                        <h3>Your Login Details:</h3>
                        <p><strong>Username:</strong> {$data['user_name']}</p>
                        <p><strong>Password:</strong> {$data['password']}</p>
                    </div>

                    <p>Click the button below to login and start earning rewards:</p>
                    <a href='{$data['login_url']}' class='button'>Login Now</a>

                    <p style='margin-top: 30px;'>Start earning rewards today! </p>

                    <p style='margin-top: 20px; color: #666; font-size: 14px;'>
                        <strong>Need help?</strong> Contact our support team.
                    </p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " MaxReward. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
