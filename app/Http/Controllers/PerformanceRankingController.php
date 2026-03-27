<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\PeerReview;
use App\Models\User;
use App\Notifications\PeerReviewReceived;
use App\Services\MonthlyPerformanceScoringService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PerformanceRankingController extends Controller
{
    private MonthlyPerformanceScoringService $scoringService;

    public function __construct(MonthlyPerformanceScoringService $scoringService)
    {
        $this->scoringService = $scoringService;
    }

    public function index(Request $request)
    {
        [$defaultMonth, $defaultYear] = $this->resolveDefaultPeriods($request);

        return view('management.performance', [
            'defaultMonth' => $defaultMonth,
            'defaultYear' => $defaultYear,
            'departments' => $this->loadDepartments(),
            'peers' => $this->loadPeers(),
        ]);
    }

    public function createPeerReview(Request $request)
    {
        return view('peer-reviews.create', [
            'defaultMonth' => Carbon::now()->format('Y-m'),
            'peers' => $this->loadPeers(),
            'myReviews' => $this->fetchRecentPeerReviews($request->user()->id),
        ]);
    }

    public function rankings(Request $request)
    {
        $validated = $request->validate([
            'mode' => 'nullable|in:month,year',
            'period' => 'nullable|string',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        [$mode, $period] = $this->resolveModeAndPeriod(
            $validated['mode'] ?? null,
            $validated['period'] ?? null
        );

        $payload = $this->scoringService->build(
            $period,
            $mode,
            $validated['department_id'] ?? null
        );
        $payload['mode'] = $mode;

        return response()->json($payload);
    }

    public function storePeerReview(Request $request)
    {
        $data = $this->validatePeerReview($request);

        $review = $this->persistPeerReview(
            reviewerId: (int) $request->user()->id,
            revieweeId: (int) $data['reviewee_id'],
            month: $data['month'],
            score: (int) $data['score'],
            note: $data['note'] ?? null,
        );

        $this->notifyReviewee($review);

        $response = [
            'message' => 'Đã ghi nhận đánh giá đồng đội.',
            'review' => $review->only(['id', 'reviewee_id', 'score', 'review_month']),
        ];

        return $request->expectsJson()
            ? response()->json($response, 201)
            : back()->with('status', $response['message']);
    }

    public function reviews(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'month' => 'nullable|date_format:Y-m',
            'year' => 'nullable|digits:4',
        ]);

        $viewer = $request->user();
        [$targetUserId, $canSeeIdentity] = $this->resolveReviewTarget($viewer, $data['user_id'] ?? null);

        $reviews = $this->buildReviewQuery($targetUserId, $data)->get();

        $items = $reviews->map(fn(PeerReview $review) => $this->formatReviewItem(
            $review,
            $canSeeIdentity,
            $viewer->id === $targetUserId
        ));

        return response()->json([
            'user_id' => $targetUserId,
            'total' => $items->count(),
            'reviews' => $items,
            'show_reviewer' => $canSeeIdentity,
        ]);
    }

    private function resolveDefaultPeriods(Request $request): array
    {
        $now = Carbon::now();

        return [
            $request->query('month', $now->format('Y-m')),
            $now->format('Y'),
        ];
    }

    private function loadDepartments()
    {
        return Department::orderBy('name')->get(['id', 'name']);
    }

    private function loadPeers()
    {
        return User::with('department:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'department_id']);
    }

    private function fetchRecentPeerReviews(int $userId)
    {
        return PeerReview::query()
            ->with('reviewer:id,name')
            ->where('reviewee_id', $userId)
            ->latest('review_month')
            ->latest('updated_at')
            ->take(20)
            ->get();
    }

    private function resolveModeAndPeriod(?string $mode, ?string $period): array
    {
        $resolvedMode = $mode ?? 'month';
        $resolvedPeriod = $period ?? ($resolvedMode === 'year'
            ? Carbon::now()->format('Y')
            : Carbon::now()->format('Y-m'));

        $this->guardValidPeriod($resolvedMode, $resolvedPeriod);

        return [$resolvedMode, $resolvedPeriod];
    }

    private function guardValidPeriod(string $mode, string $period): void
    {
        $isValid = $mode === 'month'
            ? preg_match('/^\d{4}-\d{2}$/', $period)
            : preg_match('/^\d{4}$/', $period);

        if (!$isValid) {
            abort(422, $mode === 'month' ? 'Kỳ tháng không hợp lệ.' : 'Kỳ năm không hợp lệ.');
        }
    }

    private function validatePeerReview(Request $request): array
    {
        return $request->validate([
            'reviewee_id' => 'required|exists:users,id',
            'score' => 'required|integer|min:1|max:5',
            'note' => 'nullable|string|max:1000',
            'month' => 'required|date_format:Y-m',
        ]);
    }

    private function persistPeerReview(int $reviewerId, int $revieweeId, string $month, int $score, ?string $note = null): PeerReview
    {
        if ($revieweeId === $reviewerId) {
            abort(422, 'Bạn không thể tự đánh giá cho mình.');
        }

        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        $review = PeerReview::updateOrCreate(
            [
                'reviewer_id' => $reviewerId,
                'reviewee_id' => $revieweeId,
                'review_month' => $monthStart->toDateString(),
            ],
            [
                'score' => $score,
                'note' => $note,
            ]
        );

        return $review->loadMissing('reviewee');
    }

    private function notifyReviewee(PeerReview $review): void
    {
        if ($review->reviewee && $review->reviewee_id !== $review->reviewer_id) {
            $review->reviewee->notify(new PeerReviewReceived($review));
        }
    }

    private function resolveReviewTarget(User $viewer, ?int $targetUserId): array
    {
        $canSeeIdentity = $viewer->role === 'Admin';
        $resolvedTarget = $targetUserId ?? $viewer->id;

        if (!$canSeeIdentity && $resolvedTarget !== $viewer->id) {
            abort(403, 'Bạn chỉ được phép xem nhận xét của chính mình.');
        }

        return [$resolvedTarget, $canSeeIdentity];
    }

    private function buildReviewQuery(int $targetUserId, array $filters)
    {
        $query = PeerReview::query()
            ->with('reviewer:id,name')
            ->where('reviewee_id', $targetUserId)
            ->orderByDesc('review_month')
            ->orderByDesc('updated_at');

        if (!empty($filters['month'])) {
            $month = Carbon::createFromFormat('Y-m', $filters['month']);
            $query->whereBetween('review_month', [
                $month->copy()->startOfMonth(),
                $month->copy()->endOfMonth(),
            ]);
        } elseif (!empty($filters['year'])) {
            $year = Carbon::createFromFormat('Y', $filters['year']);
            $query->whereYear('review_month', $year->year);
        } else {
            $current = Carbon::now();
            $query->whereBetween('review_month', [
                $current->copy()->startOfMonth(),
                $current->copy()->endOfMonth(),
            ]);
        }

        return $query;
    }

    private function formatReviewItem(PeerReview $review, bool $canSeeIdentity, bool $isSelfView): array
    {
        $showReviewer = $canSeeIdentity;

        if ($isSelfView && !$canSeeIdentity) {
            $showReviewer = false;
        }

        return [
            'id' => $review->id,
            'score' => (int) $review->score,
            'note' => $review->note,
            'review_month' => optional($review->review_month)->format('Y-m'),
            'reviewed_at' => optional($review->updated_at)->toDateTimeString(),
            'reviewer' => $showReviewer && $review->reviewer
                ? [
                    'id' => $review->reviewer->id,
                    'name' => $review->reviewer->name,
                ]
                : ['name' => 'Ẩn danh'],
        ];
    }
}
