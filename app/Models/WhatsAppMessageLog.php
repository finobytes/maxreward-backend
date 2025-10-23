<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WhatsAppMessageLog extends Model
{
    use HasFactory;

    protected $table = 'whats_app_message_logs';

    protected $fillable = [
        'member_id',
        'sent_by_member_id',
        'phone_number',
        'message_type',
        'message_content',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the member (receiver)
     */
    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * Get the sender (referrer)
     */
    public function sentByMember()
    {
        return $this->belongsTo(Member::class, 'sent_by_member_id');
    }

    /**
     * Create a new WhatsApp log entry
     */
    public static function logMessage($data)
    {
        return self::create([
            'member_id' => $data['member_id'],
            'sent_by_member_id' => $data['sent_by_member_id'] ?? null,
            'phone_number' => $data['phone_number'],
            'message_type' => $data['message_type'] ?? 'referral_invite',
            'message_content' => $data['message_content'],
            'status' => $data['status'] ?? 'pending',
        ]);
    }

    /**
     * Mark as sent
     */
    public function markAsSent()
    {
        $this->status = 'sent';
        $this->sent_at = Carbon::now();
        $this->save();
        
        return $this;
    }

    /**
     * Mark as failed
     */
    public function markAsFailed($errorMessage)
    {
        $this->status = 'failed';
        $this->error_message = $errorMessage;
        $this->save();
        
        return $this;
    }

    /**
     * Scope to get pending messages
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get sent messages
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope to get failed messages
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Get recent messages for a member
     */
    public static function getMemberMessages($memberId, $limit = 50)
    {
        return self::where('member_id', $memberId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get messages sent by a member (as referrer)
     */
    public static function getSentByMember($memberId, $limit = 50)
    {
        return self::where('sent_by_member_id', $memberId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get statistics
     */
    public static function getStatistics()
    {
        return [
            'total_messages' => self::count(),
            'pending' => self::pending()->count(),
            'sent' => self::sent()->count(),
            'failed' => self::failed()->count(),
        ];
    }


}
