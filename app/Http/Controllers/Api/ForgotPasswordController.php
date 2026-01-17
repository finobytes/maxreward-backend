<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Member;
use App\Models\MerchantStaff;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ForgotPasswordController extends Controller
{
    private const CODE_TTL_SECONDS = 60;

    public function sendCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userInfo = $this->findUserByUserId($request->user_id);

        if (!$userInfo['user']) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        if (empty($userInfo['email'])) {
            return response()->json([
                'success' => false,
                'message' => 'Email not found for this user',
            ], 422);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $userInfo['email']],
            ['token' => Hash::make($code), 'created_at' => Carbon::now()]
        );

        try {
            $this->sendResetCodeEmail($userInfo['email'], $userInfo['name'], $code);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send reset code',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reset code sent successfully',
            'data' => [
                'expires_in' => self::CODE_TTL_SECONDS,
                'user_id' => $request->user_id,
            ],
        ]);
    }

    public function verifyCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|string',
            'code' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userInfo = $this->findUserByUserId($request->user_id);

        if (!$userInfo['user']) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $tokenRow = DB::table('password_reset_tokens')
            ->where('email', $userInfo['email'])
            ->first();

        if (!$tokenRow || empty($tokenRow->created_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Reset code not found',
            ], 404);
        }

        $createdAt = Carbon::parse($tokenRow->created_at);
        $expired = $createdAt->diffInSeconds(Carbon::now()) > self::CODE_TTL_SECONDS;

        if ($expired) {
            return response()->json([
                'success' => false,
                'message' => 'Reset code expired',
            ], 422);
        }

        if (!Hash::check($request->code, $tokenRow->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset code',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reset code verified',
            'user_id' => $request->user_id,
            'code' => $request->code
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|string',
            'code' => 'required|digits:6',
            'new_password' => 'required|string|min:6|max:255',
            'confirmation_password' => 'required|string|same:new_password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userInfo = $this->findUserByUserId($request->user_id);

        if (!$userInfo['user']) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $tokenRow = DB::table('password_reset_tokens')
            ->where('email', $userInfo['email'])
            ->first();

        if (!$tokenRow || empty($tokenRow->created_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Reset code not found',
            ], 404);
        }

        $createdAt = Carbon::parse($tokenRow->created_at);
        $expired = $createdAt->diffInSeconds(Carbon::now()) > self::CODE_TTL_SECONDS;

        if ($expired) {
            return response()->json([
                'success' => false,
                'message' => 'Reset code expired',
            ], 422);
        }

        if (!Hash::check($request->code, $tokenRow->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset code',
            ], 422);
        }

        $userInfo['user']->password = Hash::make($request->new_password);
        $userInfo['user']->save();

        DB::table('password_reset_tokens')
            ->where('email', $userInfo['email'])
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successful',
        ]);
    }

    private function findUserByUserId($userId): array
    {
        $cleanId = trim((string) $userId);
        $firstChar = strtoupper(substr($cleanId, 0, 1));
        $user = null;

        if ($firstChar === 'A') {
            $user = Admin::where('user_name', $cleanId)->first();
        } elseif ($firstChar === 'M') {
            $user = MerchantStaff::where('user_name', $cleanId)->first();
        } else {
            $user = Member::where('user_name', $cleanId)->first();
        }

        return [
            'user' => $user,
            'email' => $user ? $user->email : null,
            'name' => $user ? ($user->name ?? $user->user_name) : null,
        ];
    }

    private function sendResetCodeEmail(string $email, ?string $name, string $code): void
    {
        $displayName = $name ?: $email;

        Mail::send([], [], function ($mail) use ($email, $displayName, $code) {
            $mail->to($email, $displayName)
                ->subject('Your MaxReward Password Reset Code')
                ->html($this->resetCodeHtml($displayName, $code));
        });
    }

    private function resetCodeHtml(string $name, string $code): string
    {
        $expires = self::CODE_TTL_SECONDS;

        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 520px; margin: 0 auto; padding: 24px; background-color: #f7f7f7; }
                .card { background-color: #ffffff; padding: 24px; border-radius: 6px; }
                .code { font-size: 24px; font-weight: bold; letter-spacing: 4px; margin: 16px 0; color: #ff5a29; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='card'>
                    <p>Hello {$name},</p>
                    <p>Your password reset code is:</p>
                    <div class='code'>{$code}</div>
                    <p>This code will expire in {$expires} seconds.</p>
                    <p>If you did not request this, please ignore this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
