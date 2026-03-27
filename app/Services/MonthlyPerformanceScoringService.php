<?php

namespace App\Services;

use App\Models\PeerReview;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builds ranking data for monthly/yearly performance leaderboard.
 */
class MonthlyPerformanceScoringService
{
    private const QUALITY_WEIGHT = 0.60;
    private const CONTRIBUTION_WEIGHT = 0.25;
    private const PROCESS_WEIGHT = 0.10;
    private const TEAM_WEIGHT = 0.05;

    private const QUALITY_RELIABILITY_THRESHOLD = 10.0; // tổng hệ số priority tương đương ~10 task chuẩn

    private array $priorityFactors = [
        'Thấp' => 0.8,
        'Trung bình' => 1.0,
        'Cao' => 1.2,
        'Khẩn cấp' => 1.3,
    ];

    private array $taskPoints = [
        'ontime' => 1.0,
        'late' => 0.4,
        'not_done' => 0.0,
    ];

    /**
     * Compile ranking payload for given period/mode.
     */
    public function build(string $period, string $mode = 'month', ?int $departmentId = null): array
    {
        [$start, $end] = $this->resolvePeriod($period, $mode);

        $assignments = $this->fetchAssignments($start, $end);
        if ($assignments->isEmpty()) {
            return $this->makePayload($period, $start, $end, $mode, $departmentId, []);
        }

        $attachmentLookup = $this->buildAttachmentLookup($start, $end);
        $commentStats = $this->buildCommentStats($start, $end);
        $peerReviewStats = $this->buildPeerReviewStats($start, $end);

        $perUser = [];
        foreach ($assignments as $row) {
            $userId = (int) $row->user_id;
            $taskId = (int) $row->task_id;
            $key = $this->makePairKey($taskId, $userId);

            if (!isset($perUser[$userId])) {
                $perUser[$userId] = [
                    'user_id' => $userId,
                    'stats' => [
                        'total' => 0,
                        'ontime' => 0,
                        'late' => 0,
                        'not_done' => 0,
                    ],
                    'quality' => [
                        'numerator' => 0.0,
                        'denominator' => 0.0,
                    ],
                    'process_counts' => [
                        'updates' => 0,
                        'attachments' => 0,
                        'acks' => 0,
                    ],
                    'weighted_work' => 0.0,
                ];
            }

            $perUser[$userId]['stats']['total']++;

            $deadline = $this->resolveDeadline($row);
            $state = $this->classifyTaskState($row, $deadline);
            $perUser[$userId]['stats'][$state]++;

            $priorityFactor = $this->priorityFactors[$row->priority] ?? 1.0;
            $reviewFactor = $this->resolveReviewFactor((int) $row->returned_count);
            $taskPoint = $this->taskPoints[$state] ?? 0.0;

            $perUser[$userId]['quality']['numerator'] += $priorityFactor * $taskPoint * $reviewFactor;
            $perUser[$userId]['quality']['denominator'] += $priorityFactor;

            if ($state !== 'not_done') {
                $estimated = max(0.25, (float) ($row->estimated_hours ?? 1));
                $perUser[$userId]['weighted_work'] += $priorityFactor * $estimated;
            }

            if (isset($commentStats['first_map'][$key]) && $deadline) {
                $commentAt = Carbon::parse($commentStats['first_map'][$key]);
                if ($commentAt->lte($deadline)) {
                    $perUser[$userId]['process_counts']['updates']++;
                }
            }

            if (isset($attachmentLookup[$key])) {
                $perUser[$userId]['process_counts']['attachments']++;
            }

            if ($this->acknowledgedOnTime($row)) {
                $perUser[$userId]['process_counts']['acks']++;
            }
        }

        $users = $this->loadUserInfo(array_keys($perUser));

        if ($departmentId) {
            foreach ($perUser as $userId => $data) {
                if (($users[$userId]['department_id'] ?? null) !== $departmentId) {
                    unset($perUser[$userId]);
                }
            }
        }

        if (empty($perUser)) {
            return $this->makePayload($period, $start, $end, $mode, $departmentId, []);
        }

        $weightedWorkMax = collect($perUser)->max(fn($row) => $row['weighted_work']) ?: 0.0;
        $weightedWorkDenominator = $weightedWorkMax > 0 ? log(1 + $weightedWorkMax) : 1;

        $rankings = [];
        foreach ($perUser as $userId => $data) {
            $stats = $data['stats'];
            $total = max(1, $stats['total']);

            $quality = $this->computeQualityScore($data['quality']);
            $contribution = $this->computeContributionScore($data['weighted_work'], $weightedWorkDenominator);
            $process = $this->computeProcessScore($data['process_counts'], $total);
            $teamInnovation = $this->computePeerScore($peerReviewStats[$userId] ?? null);

            $final = round(
                self::QUALITY_WEIGHT * $quality +
                self::CONTRIBUTION_WEIGHT * $contribution +
                self::PROCESS_WEIGHT * $process +
                self::TEAM_WEIGHT * $teamInnovation,
                2
            );

            $rankings[] = [
                'user_id' => $userId,
                'user' => $users[$userId] ?? ['name' => 'User #' . $userId],
                'metrics' => [
                    'quality' => $quality,
                    'contribution' => $contribution,
                    'process' => $process,
                    'team' => $teamInnovation,
                    'final' => $final,
                ],
                'breakdown' => [
                    'quality' => array_merge($data['quality'], [
                        'reliability_threshold' => self::QUALITY_RELIABILITY_THRESHOLD,
                    ]),
                    'contribution' => [
                        'weighted_work' => round($data['weighted_work'], 2),
                        'max_weighted_work' => round($weightedWorkMax, 2),
                    ],
                    'process' => [
                        'updates_percent' => $this->percentage($data['process_counts']['updates'], $total),
                        'attachments_percent' => $this->percentage($data['process_counts']['attachments'], $total),
                        'ack_percent' => $this->percentage($data['process_counts']['acks'], $total),
                    ],
                    'team' => [
                        'average_score' => $peerReviewStats[$userId]['average'] ?? 0,
                        'reviews' => $peerReviewStats[$userId]['count'] ?? 0,
                    ],
                    'tasks' => $stats,
                ],
            ];
        }

        usort($rankings, fn($a, $b) => $b['metrics']['final'] <=> $a['metrics']['final']);
        foreach ($rankings as $index => &$row) {
            $row['rank'] = $index + 1;
        }

        return $this->makePayload($period, $start, $end, $mode, $departmentId, $rankings);
    }

    /**
     * Resolve start/end Carbon instances for requested window.
     */
    private function resolvePeriod(string $value, string $mode): array
    {
        if ($mode === 'year') {
            $period = Carbon::createFromFormat('Y', $value)->startOfYear();
            return [$period->copy()->startOfYear(), $period->copy()->endOfYear()];
        }

        $period = Carbon::createFromFormat('Y-m', $value)->startOfMonth();
        return [$period->copy()->startOfMonth(), $period->copy()->endOfMonth()];
    }

    /**
     * Load task assignments falling inside window.
     */
    private function fetchAssignments(Carbon $start, Carbon $end): Collection
    {
        return DB::table('task_user')
            ->join('tasks', 'tasks.id', '=', 'task_user.task_id')
            ->whereBetween('tasks.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->select([
                'task_user.task_id',
                'task_user.user_id',
                'task_user.status as user_status',
                'task_user.progress',
                'task_user.completed_at',
                'task_user.returned_count',
                'task_user.read_at',
                'task_user.created_at as assigned_at',
                'task_user.updated_at as pivot_updated_at',
                'tasks.priority',
                'tasks.deadline_at',
                'tasks.task_date',
                'tasks.estimated_hours',
                'tasks.status as task_status',
                'tasks.created_at as task_created_at',
                'tasks.updated_at as task_updated_at',
            ])
            ->get();
    }

    /**
     * Index first attachment timestamps by task/user pair.
     */
    private function buildAttachmentLookup(Carbon $start, Carbon $end): array
    {
        $rows = DB::table('task_files')
            ->join('tasks', 'tasks.id', '=', 'task_files.task_id')
            ->whereBetween('tasks.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->whereNotNull('task_files.uploaded_by')
            ->select('task_files.task_id', 'task_files.uploaded_by', 'task_files.created_at')
            ->get();

        $lookup = [];
        foreach ($rows as $row) {
            $lookup[$this->makePairKey($row->task_id, $row->uploaded_by)] = $row->created_at;
        }

        return $lookup;
    }

    /**
     * Capture earliest comment per task/user to score process discipline.
     */
    private function buildCommentStats(Carbon $start, Carbon $end): array
    {
        $rows = DB::table('task_comments')
            ->join('tasks', 'tasks.id', '=', 'task_comments.task_id')
            ->whereBetween('tasks.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->select('task_comments.task_id', 'task_comments.user_id', DB::raw('MIN(task_comments.created_at) as first_comment_at'))
            ->groupBy('task_comments.task_id', 'task_comments.user_id')
            ->get();

        $firstMap = [];
        foreach ($rows as $row) {
            $firstMap[$this->makePairKey($row->task_id, $row->user_id)] = $row->first_comment_at;
        }

        return [
            'rows' => $rows,
            'first_map' => $firstMap,
        ];
    }

    private function resolveDeadline($row): ?Carbon
    {
        $source = $row->deadline_at ?? $row->task_date ?? null;
        if (!$source) {
            return null;
        }

        return Carbon::parse($source)->endOfDay();
    }

    private function classifyTaskState($row, ?Carbon $deadline): string
    {
        $status = $row->user_status ?? $row->task_status;
        if ($status !== 'Đã hoàn thành') {
            return 'not_done';
        }

        $completedAt = $this->resolveCompletionTime($row);
        if (!$completedAt || !$deadline) {
            return 'ontime';
        }

        return $completedAt->gt($deadline) ? 'late' : 'ontime';
    }

    private function resolveCompletionTime($row): ?Carbon
    {
        if ($row->completed_at) {
            return Carbon::parse($row->completed_at);
        }

        if ($row->pivot_updated_at && ($row->user_status ?? null) === 'Đã hoàn thành') {
            return Carbon::parse($row->pivot_updated_at);
        }

        return null;
    }

    private function resolveReviewFactor(int $returnedCount): float
    {
        if ($returnedCount <= 0) {
            return 1.0;
        }

        if ($returnedCount === 1) {
            return 0.9;
        }

        return 0.75;
    }

    private function computeQualityScore(array $quality): float
    {
        $denominator = $quality['denominator'] ?: 0.0;
        if ($denominator <= 0) {
            return 0.0;
        }

        $raw = 100 * ($quality['numerator'] / $denominator);
        $reliability = min(1.0, $denominator / self::QUALITY_RELIABILITY_THRESHOLD);

        return round($raw * $reliability, 2);
    }

    private function computeContributionScore(float $weightedWork, float $denominator): float
    {
        if ($weightedWork <= 0) {
            return 0.0;
        }

        $divider = $denominator > 0 ? $denominator : 1;

        return round(100 * (log(1 + $weightedWork) / $divider), 2);
    }

    private function computeProcessScore(array $counts, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        $updatePercent = $this->percentage($counts['updates'], $total);
        $attachmentPercent = $this->percentage($counts['attachments'], $total);
        $ackPercent = $this->percentage($counts['acks'], $total);

        return round(($updatePercent + $attachmentPercent + $ackPercent) / 3, 2);
    }

    private function buildPeerReviewStats(Carbon $start, Carbon $end): array
    {
        if (!Schema::hasTable('peer_reviews')) {
            return [];
        }

        $rows = PeerReview::query()
            ->whereBetween('review_month', [$start->copy()->startOfMonth(), $end->copy()->endOfMonth()])
            ->select(
                'reviewee_id',
                DB::raw('AVG(score) as average_score'),
                DB::raw('COUNT(*) as total_reviews')
            )
            ->groupBy('reviewee_id')
            ->get();

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int) $row->reviewee_id] = [
                'average' => (float) $row->average_score,
                'count' => (int) $row->total_reviews,
            ];
        }

        return $stats;
    }

    private function computePeerScore(?array $stats): float
    {
        if (!$stats || ($stats['average'] ?? 0) <= 0) {
            return 0.0;
        }

        $base = min(5, $stats['average']) / 5; // chuẩn hoá về 0-1
        $reliability = min(1.0, ($stats['count'] ?? 0) / 3) ?: 0.3; // cần ít nhất 3 lượt cho 100%

        return round(100 * $base * $reliability, 2);
    }

    private function percentage(int $part, int $total): float
    {
        if ($total <= 0 || $part <= 0) {
            return 0.0;
        }

        return round(($part / $total) * 100, 2);
    }

    private function acknowledgedOnTime($row): bool
    {
        if (!$row->assigned_at) {
            return false;
        }

        $assigned = Carbon::parse($row->assigned_at);
        $slaEnd = $assigned->copy()->addHours(24);

        if ($row->read_at && Carbon::parse($row->read_at)->lte($slaEnd)) {
            return true;
        }

        if ($row->completed_at && Carbon::parse($row->completed_at)->lte($slaEnd)) {
            return true;
        }

        return false;
    }

    private function makePairKey($taskId, $userId): string
    {
        return $taskId . ':' . $userId;
    }

    private function loadUserInfo(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        return User::query()
            ->with('department:id,name')
            ->whereIn('id', $userIds)
            ->get(['id', 'name', 'email', 'avatar', 'department_id'])
            ->mapWithKeys(fn(User $user) => [
                $user->id => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'department_id' => $user->department_id,
                    'department' => optional($user->department)->name,
                ],
            ])
            ->all();
    }

    private function makePayload(string $period, Carbon $start, Carbon $end, string $mode, ?int $departmentId, array $rankings): array
    {
        return [
            'period' => $period,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'rankings' => $rankings,
            'mode' => $mode,
            'department_id' => $departmentId,
        ];
    }
}
