@extends('layouts.app')

@section('content')
@php($currentUserId = auth()->id())

<div class="container py-4" id="performance-leaderboard" aria-live="polite">
    <div class="surface-card p-4 p-lg-5 mb-4">
        <div class="row g-4 align-items-center">
            <div class="col-lg-8">
                <p class="eyebrow text-muted mb-2">Bảng xếp hạng hiệu suất</p>
                <h1 class="display-6 fw-bold mb-0 text-gradient">Nhịp hiệu suất & đồng đội</h1>
            </div>
            <div class="col-lg-4 text-lg-end">
                <div class="insight-pill">Điểm số cập nhật theo thời gian thực</div>
            </div>
        </div>
        <div class="filter-toolbar mt-4 p-3 p-lg-4 rounded-4 shadow-sm">
            <div class="d-flex flex-wrap gap-3 align-items-end">
                <div class="filter-chip flex-grow-1 flex-lg-grow-0">
                    <label class="form-label text-muted small mb-2">Chế độ</label>
                    <div class="mode-toggle" role="group" aria-label="Chế độ thống kê">
                        <input type="radio" class="btn-check" name="performance-mode" id="performance-mode-month" value="month" checked>
                        <label class="btn" for="performance-mode-month">Theo tháng</label>

                        <input type="radio" class="btn-check" name="performance-mode" id="performance-mode-year" value="year">
                        <label class="btn" for="performance-mode-year">Theo năm</label>
                    </div>
                </div>
                <div class="filter-chip mode-control" data-mode="month">
                    <label for="performance-month" class="form-label text-muted small mb-2">Chọn tháng</label>
                    <input type="month" class="form-control" id="performance-month" value="{{ $defaultMonth }}" data-default-month="{{ $defaultMonth }}">
                </div>
                <div class="filter-chip mode-control d-none" data-mode="year">
                    <label for="performance-year" class="form-label text-muted small mb-2">Chọn năm</label>
                    <input type="number" class="form-control" id="performance-year" value="{{ $defaultYear }}" data-default-year="{{ $defaultYear }}" min="2015" max="2100">
                </div>
                <div class="filter-chip flex-grow-1">
                    <label for="performance-department" class="form-label text-muted small mb-2">Phòng ban</label>
                    <select class="form-select" id="performance-department">
                        <option value="">Tất cả phòng ban</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4 metrics-row" aria-hidden="true">
        <div class="col-6 col-md-3">
            <div class="metric-chip metric-quality">
                <span class="metric-label">Chất lượng</span>
                <span class="metric-value">60%</span>
                <span class="metric-desc">Chuẩn đầu ra & QA</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="metric-chip metric-contribution">
                <span class="metric-label">Đóng góp</span>
                <span class="metric-value">25%</span>
                <span class="metric-desc">Sáng kiến & tác động</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="metric-chip metric-process">
                <span class="metric-label">Quy trình</span>
                <span class="metric-value">10%</span>
                <span class="metric-desc">Kỷ luật vận hành</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="metric-chip metric-team">
                <span class="metric-label">Đồng đội</span>
                <span class="metric-value">5%</span>
                <span class="metric-desc">Tinh thần hỗ trợ</span>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4" id="top-highlight">
        @for ($i = 1; $i <= 3; $i++)
            <div class="col-md-4">
                <div class="highlight-card" data-rank="{{ $i }}">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="rank-pill">Top {{ $i }}</span>
                        <span class="text-muted small" data-field="score">--%</span>
                    </div>
                    <h3 class="h5 mb-1" data-field="name">Đang cập nhật...</h3>
                    <p class="text-muted small mb-0" data-field="department">Chưa có dữ liệu</p>
                </div>
            </div>
        toggleModeControls();
        loadRankings();
#performance-leaderboard .leaderboard-shell {
    border-radius: 28px;
}

#performance-leaderboard .leaderboard-table-wrapper {
    max-height: 640px;
                const response = await fetch('/peer-reviews', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(payload),
                });
    
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || 'Không thể lưu đánh giá');
                }
    
                dom.peerReviewAlert.textContent = 'Đã lưu đánh giá đồng đội.';
                dom.peerReviewAlert.classList.remove('d-none');
                dom.peerReviewAlert.classList.remove('alert-danger');
                dom.peerReviewAlert.classList.add('alert-success');
                dom.peerReviewForm.reset();
                if (dom.peerReviewScore && dom.peerReviewScoreValue) {
                    dom.peerReviewScoreValue.textContent = defaultPeerScore;
                    dom.peerReviewScore.value = defaultPeerScore;
                }
                dom.peerReviewMonth.value = dom.monthInput.value || dom.monthInput.dataset.defaultMonth;
                loadRankings();
    
                setTimeout(() => {
                    const modalInstance = bootstrap.Modal.getInstance(dom.peerReviewModal);
                    modalInstance?.hide();
                }, 800);
            } catch (error) {
                dom.peerReviewAlert.textContent = error.message;
                dom.peerReviewAlert.classList.remove('d-none');
                dom.peerReviewAlert.classList.remove('alert-success');
                dom.peerReviewAlert.classList.add('alert-danger');
            }
        });
    
        dom.tableBody.addEventListener('click', (event) => {
            const button = event.target.closest('.view-peer-reviews');
            if (!button) {
                return;
            }
    
            const userId = button.dataset.userId;
            const username = button.dataset.username;
            if (!userId) {
                return;
            }
    
            loadPeerReviewDetails(userId, username);
        });
    
        dom.monthInput.addEventListener('change', loadRankings);
        dom.yearInput.addEventListener('change', loadRankings);
        dom.departmentSelect.addEventListener('change', loadRankings);
    
        dom.modeRadios.forEach((radio) => {
            radio.addEventListener('change', (event) => {
                if (!event.target.checked) {
                    return;
                }
    
                currentMode = event.target.value;
                toggleModeControls();
                loadRankings();
            });
        });
    
        toggleModeControls();
        loadRankings();

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const defaultPeerScore = dom.peerReviewScore ? dom.peerReviewScore.value : '3';
    let peerReviewDetailsModal;

    let currentMode = 'month';

    function toggleModeControls() {
        modeControls.forEach((control) => {
            const shouldShow = control.dataset.mode === currentMode;
            control.classList.toggle('d-none', !shouldShow);
        });
    }

    function getPeriodValue() {
        return currentMode === 'year'
            ? (yearInput.value || yearInput.dataset.defaultYear)
            : (monthInput.value || monthInput.dataset.defaultMonth);
    }

    async function loadRankings() {
        const periodValue = getPeriodValue();
        if (!periodValue) {
            return;
        }

        const params = new URLSearchParams({
            mode: currentMode,
            period: periodValue,
        });

        if (departmentSelect.value) {
            params.append('department_id', departmentSelect.value);
        }

        tableBody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">Đang tải...</td></tr>';

        try {
            const response = await fetch(`/management/performance/rankings?${params.toString()}`);
            if (!response.ok) {
                throw new Error('Không thể tải dữ liệu');
            }

            const payload = await response.json();
            renderRows(payload.rankings || []);
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger py-4">${error.message}</td></tr>`;
        }
    }

    function renderRows(rows) {
        if (!rows.length) {
            tableBody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">Chưa có dữ liệu cho lựa chọn này.</td></tr>';
            renderHighlights([]);
            return;
        }

        tableBody.innerHTML = rows.map((row) => {
            const rowClass = getRankClass(row.rank);
            const department = row.user?.department ?? 'Chưa cập nhật';
            const tasks = row.breakdown?.tasks ?? { ontime: 0, late: 0, not_done: 0 };
            const team = row.breakdown?.team ?? null;

            return `
                <tr class="${rowClass}">
                    <td class="fw-semibold">${row.rank}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            ${row.user?.avatar ? `<img src="${row.user.avatar}" alt="${row.user.name}" class="rounded-circle" width="36" height="36">` : ''}
                            <div>
                                <div class="fw-semibold">${row.user?.name ?? 'N/A'}</div>
                                <div class="text-muted small">${row.user?.email ?? ''}</div>
                                <div class="text-muted small">${department}</div>
                            </div>
                        </div>
                    </td>
                    <td>${formatScore(row.metrics?.quality)}</td>
                    <td>${formatScore(row.metrics?.contribution)}</td>
                    <td>${formatScore(row.metrics?.process)}</td>
                    <td>${formatScore(row.metrics?.team)}</td>
                    <td class="fw-bold">${formatScore(row.metrics?.final)}</td>
                    <td class="text-muted small">
                        Đúng hạn: ${tasks.ontime}<br>
                        Trễ: ${tasks.late}<br>
                        Chưa xong: ${tasks.not_done}<br>
                        <span class="d-inline-block mt-2">Đánh giá đồng đội: ${formatPeerStats(team)}</span>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-2 view-peer-reviews" data-user-id="${row.user_id}" data-username="${row.user?.name ?? ''}">
                            Xem nhận xét
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        renderHighlights(rows);
    }
    const peerReviewDetailsModalEl = document.getElementById('peerReviewDetailsModal');
    const peerReviewDetailsList = document.getElementById('peer-review-details-list');
    const peerReviewDetailsEmpty = document.getElementById('peer-review-details-empty');
    const peerReviewDetailsLoading = document.getElementById('peer-review-details-loading');
    const peerReviewDetailsName = document.getElementById('peer-review-details-name');
    const peerReviewDetailsPeriod = document.getElementById('peer-review-details-period');
    const peerReviewDetailsError = document.getElementById('peer-review-details-error');
    let peerReviewDetailsModal;

    function formatScore(value) {
        return typeof value === 'number' && !Number.isNaN(value) ? `${value.toFixed(1)}%` : '--';
    }

    function getRankClass(rank) {
        if (rank === 1) return 'leaderboard-row-1';
        if (rank === 2) return 'leaderboard-row-2';
        if (rank === 3) return 'leaderboard-row-3';
        if (rank <= 5) return 'leaderboard-row-top';
        return '';
    }

    function formatPeerStats(team) {
        if (!team || !team.average_score) {
            return 'Chưa có dữ liệu';
        }

        const avg = Number(team.average_score).toFixed(1);
        const count = team.reviews ?? 0;
        return `${avg}/5 (${count} lượt)`;
    }

    function renderHighlights(rows) {
        highlightCards.forEach((card, index) => {
            const nameEl = card.querySelector('[data-field="name"]');
            const scoreEl = card.querySelector('[data-field="score"]');
            const deptEl = card.querySelector('[data-field="department"]');
            const slot = rows[index];

            if (!slot) {
                nameEl.textContent = 'Đang chờ dữ liệu';
                scoreEl.textContent = '--%';
                deptEl.textContent = 'Chưa có thống kê';
                return;
            }

            nameEl.textContent = slot.user?.name ?? 'N/A';
            scoreEl.textContent = formatScore(slot.metrics?.final);
            const deptText = slot.user?.department ?? 'Chưa cập nhật';
            deptEl.textContent = `${deptText} • ${formatScore(slot.metrics?.quality)} chất lượng`;
        });
    }

    if (peerReviewScore && peerReviewScoreValue) {
        peerReviewScoreValue.textContent = peerReviewScore.value;
    }

    peerReviewScore?.addEventListener('input', () => {
        peerReviewScoreValue.textContent = peerReviewScore.value;
    });

    async function loadPeerReviewDetails(userId, userName) {
        if (!peerReviewDetailsModal) {
            peerReviewDetailsModal = new bootstrap.Modal(peerReviewDetailsModalEl);
        }

        peerReviewDetailsName.textContent = userName || 'Nhân viên';
        peerReviewDetailsError.classList.add('d-none');
        peerReviewDetailsLoading.classList.remove('d-none');
        peerReviewDetailsEmpty.classList.add('d-none');
        peerReviewDetailsList.innerHTML = '';

        const params = new URLSearchParams({
            user_id: userId,
        });

        const periodValue = getPeriodValue();
        if (currentMode === 'year') {
            params.append('year', periodValue);
            peerReviewDetailsPeriod.textContent = `Năm ${periodValue}`;
        } else {
            params.append('month', periodValue);
            peerReviewDetailsPeriod.textContent = `Tháng ${formatMonthDisplay(periodValue)}`;
        }

        try {
            const response = await fetch(`/peer-reviews/list?${params.toString()}`);
            if (!response.ok) {
                throw new Error('Không thể tải nhận xét.');
            }

            const data = await response.json();
            renderPeerReviewDetails(data.reviews || []);
        } catch (error) {
            peerReviewDetailsLoading.classList.add('d-none');
            peerReviewDetailsError.textContent = error.message;
            peerReviewDetailsError.classList.remove('d-none');
        }

        peerReviewDetailsModal.show();
    }

    function renderPeerReviewDetails(reviews) {
        peerReviewDetailsLoading.classList.add('d-none');

        if (!reviews.length) {
            peerReviewDetailsEmpty.classList.remove('d-none');
            return;
        }

        peerReviewDetailsList.innerHTML = reviews.map((review) => {
            const reviewerName = review.reviewer?.name ?? 'Ẩn danh';
            const note = review.note || 'Không có nhận xét chi tiết.';
            const monthLabel = review.review_month ? formatMonthDisplay(review.review_month) : 'Chưa rõ tháng';
            const reviewedAt = review.reviewed_at ? new Date(review.reviewed_at).toLocaleString('vi-VN') : '';

            return `
                <li class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold">${monthLabel}</div>
                            <div class="text-muted small">${reviewerName}</div>
                        </div>
                        <span class="badge bg-primary">${review.score}/5</span>
                    </div>
                    <p class="mb-1 mt-2">${note}</p>
                    <div class="text-muted small">Cập nhật ${reviewedAt}</div>
                </li>
            `;
        }).join('');
    }

    function formatMonthDisplay(value) {
        if (!value) {
            return '';
        }

        const [year, month] = value.split('-');
        return `${month}/${year}`;
    }

    peerReviewModalEl?.addEventListener('show.bs.modal', () => {
        peerReviewAlert.classList.add('d-none');
        peerReviewAlert.classList.remove('alert-danger', 'alert-success');
        peerReviewMonth.value = monthInput.value || monthInput.dataset.defaultMonth;
    });

    peerReviewForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!peerReviewAlert.classList.contains('d-none')) {
            peerReviewAlert.classList.add('d-none');
        }

        const formData = new FormData(peerReviewForm);
        const payload = {
            reviewee_id: formData.get('reviewee_id'),
            score: Number(formData.get('score')),
            note: formData.get('note')?.trim() || null,
            month: formData.get('month'),
        };

        try {
            const response = await fetch('/peer-reviews', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                throw new Error(errorData.message || 'Không thể lưu đánh giá');
            }

            peerReviewAlert.textContent = 'Đã lưu đánh giá đồng đội.';
            peerReviewAlert.classList.remove('d-none');
            peerReviewAlert.classList.remove('alert-danger');
            peerReviewAlert.classList.add('alert-success');
            peerReviewForm.reset();
            if (peerReviewScore && peerReviewScoreValue) {
                peerReviewScoreValue.textContent = defaultPeerScore;
                peerReviewScore.value = defaultPeerScore;
            }
            peerReviewMonth.value = monthInput.value || monthInput.dataset.defaultMonth;
            loadRankings();

            setTimeout(() => {
                const modalInstance = bootstrap.Modal.getInstance(peerReviewModalEl);
                modalInstance?.hide();
            }, 800);
        } catch (error) {
            peerReviewAlert.textContent = error.message;
            peerReviewAlert.classList.remove('d-none');
            peerReviewAlert.classList.remove('alert-success');
            peerReviewAlert.classList.add('alert-danger');
        }
    });

    tableBody.addEventListener('click', (event) => {
        const button = event.target.closest('.view-peer-reviews');
        if (!button) {
            return;
        }

        const userId = button.dataset.userId;
        const username = button.dataset.username;
        if (!userId) {
            return;
        }

        loadPeerReviewDetails(userId, username);
    });

    monthInput.addEventListener('change', loadRankings);
    yearInput.addEventListener('change', loadRankings);
    departmentSelect.addEventListener('change', loadRankings);

    modeRadios.forEach((radio) => {
        radio.addEventListener('change', (event) => {
            if (!event.target.checked) {
                return;
            }

            currentMode = event.target.value;
            toggleModeControls();
            loadRankings();
        });
    });

    toggleModeControls();
    loadRankings();
})();
</script>
@endpush
