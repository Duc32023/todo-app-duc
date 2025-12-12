<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KPI;
use App\Models\Task;
use App\Models\User;
use App\Services\MonthlyKpiAggregator;
use App\Notifications\TaskPingNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class KpiHealthController extends Controller
{
    public function __construct(private MonthlyKpiAggregator $aggregator)
    {
    }

    public function index()
    {
        return view('management.kpi-health');
    }

    public function snapshot(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $range = $this->buildRange($month);
        $departmentId = $request->filled('department_id')
            ? (int) $request->input('department_id')
            : null;

        $visibleUserIds = $this->resolveVisibleUserIds($request->user(), $departmentId);

        $kpisQuery = KPI::with('user')
            ->whereDate('start_date', $range['start']->toDateString())
            ->whereDate('end_date', $range['end']->toDateString());

        if (is_array($visibleUserIds)) {
            $kpisQuery->whereIn('user_id', $visibleUserIds);
        }

        $kpis = $kpisQuery->get();

        $kpis->each(fn(KPI $kpi) => $this->aggregator->recalculate($kpi, false));

        $summary = $this->buildSummary($kpis, $range['start']);
        $distribution = $this->buildDistribution($kpis);
        $riskKpis = $this->formatRiskKpis($kpis);
        $blockedTasks = $this->collectBlockedTasks($range['start'], $range['end'], $visibleUserIds);

        return response()->json([
            'month' => $month,
            'summary' => $summary,
            'distribution' => $distribution,
            'risk_kpis' => $riskKpis,
            'blocked_tasks' => $blockedTasks,
        ]);
    }

    public function reassignTask(Request $request, Task $task)
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        DB::transaction(function () use ($task, $validated) {
            $sync = [];
            foreach ($validated['user_ids'] as $userId) {
                $sync[$userId] = [
                    'status' => 'Chưa hoàn thành',
                    'progress' => 0,
                ];
            }

            $task->users()->sync($sync);
            $task->status = 'Chưa hoàn thành';
            $task->save();
        });

        $task->load('users:id,name');

        return response()->json([
            'success' => true,
            'task_id' => $task->id,
            'new_owners' => $task->users->pluck('name', 'id'),
        ]);
    }

    public function pingTask(Request $request, Task $task)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $recipients = $task->users()->select('users.id', 'users.name', 'users.email')->get();

        if ($recipients->isEmpty()) {
            return response()->json([
                'message' => 'Task chưa có người phụ trách để ping.'
            ], 422);
        }

        Notification::send($recipients, new TaskPingNotification(
            $task,
            $validated['message'],
            $request->user()
        ));

        return response()->json([
            'success' => true,
        ]);
    }

    private function buildRange(string $month): array
    {
        $target = Carbon::createFromFormat('Y-m', $month);

        return [
            'start' => $target->copy()->startOfMonth(),
            'end' => $target->copy()->endOfMonth(),
        ];
    }

    private function buildSummary(Collection $kpis, Carbon $start): array
    {
        $total = $kpis->count();
        $onTrack = $kpis->where('percent', '>=', 90)->count();
        $atRisk = $kpis->whereBetween('percent', [70, 90])->count();
        $critical = $kpis->where('percent', '<', 70)->count();
        $avgPercent = $total > 0 ? round($kpis->avg('percent'), 1) : 0;

        return [
            'total' => $total,
            'on_track' => $onTrack,
            'at_risk' => $atRisk,
            'critical' => $critical,
            'avg_percent' => $avgPercent,
            'month_label' => $start->translatedFormat('F Y'),
        ];
    }

    private function buildDistribution(Collection $kpis): array
    {
        $buckets = [
            'excellent' => 0,
            'good' => 0,
            'warning' => 0,
            'critical' => 0,
        ];

        foreach ($kpis as $kpi) {
            $percent = (float) $kpi->percent;
            if ($percent >= 95) {
                $buckets['excellent']++; 
            } elseif ($percent >= 85) {
                $buckets['good']++;
            } elseif ($percent >= 70) {
                $buckets['warning']++;
            } else {
                $buckets['critical']++;
            }
        }

        return $buckets;
    }

    private function formatRiskKpis(Collection $kpis): array
    {
        return $kpis
            ->filter(fn (KPI $kpi) => (float) $kpi->percent < 90)
            ->sortBy('percent')
            ->take(8)
            ->map(function (KPI $kpi) {
                $end = Carbon::parse($kpi->end_date);
                $daysDiff = $end->diffInDays(now(), false);

                return [
                    'id' => $kpi->id,
                    'name' => $kpi->name,
                    'owner' => optional($kpi->user)->name,
                    'percent' => (float) $kpi->percent,
                    'deadline' => $end->toDateString(),
                    'days_left' => $daysDiff,
                    'note' => $kpi->note,
                ];
            })
            ->values()
            ->all();
    }

    private function collectBlockedTasks(Carbon $start, Carbon $end, ?array $visibleUserIds = null): array
    {
        $query = Task::query()
            ->with(['assignedByUser:id,name', 'users:id,name'])
            ->where('status', '!=', 'Đã hoàn thành')
            ->whereNotNull('deadline_at')
            ->whereBetween('deadline_at', [$start->copy()->subDays(7), $end->copy()->addDays(7)])
            ->orderBy('deadline_at');

        if (is_array($visibleUserIds)) {
            $query->where(function ($sub) use ($visibleUserIds) {
                $sub->whereIn('tasks.user_id', $visibleUserIds)
                    ->orWhereHas('users', fn($q) => $q->whereIn('users.id', $visibleUserIds));
            });
        }

        return $query->take(10)->get()->map(function (Task $task) {
            $deadline = $task->deadline_at ? Carbon::parse($task->deadline_at) : null;
            $today = now();

            $isOverdue = false;
            $daysDelta = 0;

            if ($deadline) {
                if ($deadline->lt($today)) {
                    $isOverdue = true;
                    $daysDelta = $deadline->diffInDays($today); // số ngày trễ (dương)
                } else {
                    $daysDelta = -$today->diffInDays($deadline); // số ngày còn lại (âm)
                }
            }

            return [
                'id' => $task->id,
                'title' => $task->title ?? 'Không tên',
                'priority' => $task->priority,
                'deadline' => $deadline?->toDateString(),
                'assigned_by' => optional($task->assignedByUser)->name,
                'owners' => $task->users->pluck('name')->all(),
                'status' => $task->status,
                'is_overdue' => $isOverdue,
                'days_overdue' => $daysDelta,
            ];
        })->all();
    }

    private function resolveVisibleUserIds(?User $currentUser, ?int $departmentId = null): ?array
    {
        if (!$currentUser) {
            return null;
        }

        if ($currentUser->role === 'Admin') {
            if (!$departmentId) {
                return null; // Admin xem toàn bộ
            }

            return User::query()
                ->where('department_id', $departmentId)
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        if ($currentUser->role !== 'Trưởng phòng') {
            return [$currentUser->id];
        }

        if (!$currentUser->department_id) {
            return [$currentUser->id];
        }

        return User::query()
            ->where('department_id', $currentUser->department_id)
            ->pluck('id')
            ->push($currentUser->id)
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
