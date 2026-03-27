<?php

namespace App\Notifications;

use App\Models\PeerReview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PeerReviewReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private PeerReview $review)
    {
        $this->queue = 'notifications';
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'peer_review',
            'message' => 'Bạn vừa nhận được một đánh giá đồng đội mới.',
            'score' => (int) $this->review->score,
            'month' => $this->review->review_month?->format('Y-m'),
            'reviewer_id' => $this->review->reviewer_id,
            'reviewee_id' => $this->review->reviewee_id,
            'review_id' => $this->review->id,
        ];
    }
}
