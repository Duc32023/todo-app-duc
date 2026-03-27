@extends('layouts.app')

@section('content')
<div class="container py-4" id="peer-review-page">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h1 class="h4 mb-3">Đánh giá đồng đội</h1>
                    <p class="text-muted">Ghi nhận sự hỗ trợ thật sự của đồng nghiệp mỗi tháng để bảng xếp hạng phản ánh đúng tinh thần hợp tác.</p>

                    @if (session('status'))
                        <div class="alert alert-success">{{ session('status') }}</div>
                    @endif

                    <form action="{{ route('peer-reviews.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="peer-review-user" class="form-label">Chọn nhân viên</label>
                            <select class="form-select @error('reviewee_id') is-invalid @enderror" id="peer-review-user" name="reviewee_id" required>
                                <option value="">-- Chọn --</option>
                                @foreach ($peers as $peer)
                                    @continue($peer->id === auth()->id())
                                    <option value="{{ $peer->id }}" {{ old('reviewee_id') == $peer->id ? 'selected' : '' }}>
                                        {{ $peer->name }}
                                        @if ($peer->department)
                                            - {{ $peer->department->name }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('reviewee_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="peer-review-month" class="form-label">Kỳ đánh giá</label>
                            <input type="month" class="form-control @error('month') is-invalid @enderror" id="peer-review-month" name="month" value="{{ old('month', $defaultMonth) }}" required>
                            @error('month')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="peer-review-score" class="form-label">Điểm hợp tác (1-5)</label>
                            <input type="range" class="form-range" id="peer-review-score" name="score" min="1" max="5" step="1" value="{{ old('score', 4) }}">
                            <div class="text-end small">Điểm hiện tại: <span id="peer-review-score-value">{{ old('score', 4) }}</span>/5</div>
                            @error('score')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="peer-review-note" class="form-label">Nhận xét (không bắt buộc)</label>
                            <textarea class="form-control @error('note') is-invalid @enderror" id="peer-review-note" name="note" rows="3" placeholder="Ví dụ: Luôn kèm team xử lý bug trước thời hạn.">{{ old('note') }}</textarea>
                            @error('note')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Gửi đánh giá</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Nhận xét bạn đã nhận</h2>
                    <p class="text-muted small">Tên người đánh giá được ẩn để đảm bảo tính khách quan. Bạn vẫn có thể dựa vào nội dung nhận xét để cải thiện.</p>

                    @if ($myReviews->isEmpty())
                        <div class="text-muted">Chưa có ai gửi đánh giá cho bạn.</div>
                    @else
                        <div class="list-group list-group-flush">
                            @foreach ($myReviews as $review)
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-semibold">Tháng {{ optional($review->review_month)->format('m/Y') }}</span>
                                        <span class="badge bg-primary">{{ $review->score }}/5</span>
                                    </div>
                                    <p class="mb-1 mt-2">{{ $review->note ?? 'Không có nhận xét chi tiết.' }}</p>
                                    <div class="text-muted small">Ẩn danh • Cập nhật {{ optional($review->updated_at)->format('d/m/Y H:i') }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const scoreInput = document.getElementById('peer-review-score');
    const scoreValue = document.getElementById('peer-review-score-value');
    if (scoreInput && scoreValue) {
        scoreInput.addEventListener('input', () => {
            scoreValue.textContent = scoreInput.value;
        });
    }
})();
</script>
@endpush
