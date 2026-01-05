@extends('layouts.app')

@section('content')
<h1>Sửa công việc</h1>

@php
    $dropdowns = [
        ['name' => 'shift', 'label' => 'Ca', 'api' => '/api/shifts', 'field' => 'shift_name', 'title' => 'Quản lý Ca Làm', 'value' => $task->shift],
        ['name' => 'type', 'label' => 'Loại', 'api' => '/api/types', 'field' => 'type_name', 'title' => 'Quản lý Loại Task', 'value' => $task->type],
        ['name' => 'title', 'label' => 'Tên task', 'api' => '/api/titles', 'field' => 'title_name', 'title' => 'Quản lý Tên Task', 'value' => $task->title],
        ['name' => 'supervisor', 'label' => 'Người phụ trách', 'api' => '/api/supervisors', 'field' => 'supervisor_name', 'title' => 'Quản lý Người phụ trách', 'value' => $task->supervisor],
    ];
@endphp

<form method="POST" action="{{ route('tasks.update', $task->id) }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="redirect_back" value="{{ route('tasks.index') }}">

    <div class="mb-3">
        <label>Ngày:</label>
        <input type="date" name="task_date" class="form-control" value="{{ $task->task_date }}">
    </div>

    @foreach ($dropdowns as $dropdown)
        <div class="mb-3">
            <label>{{ $dropdown['label'] }}:</label>
            <div class="input-group">
                <select
                    name="{{ $dropdown['name'] }}"
                    class="form-control"
                    id="{{ $dropdown['name'] }}-select"
                    data-api="{{ $dropdown['api'] }}"
                    data-field="{{ $dropdown['field'] }}"
                    data-title="{{ $dropdown['title'] }}"
                    data-selected="{{ e($dropdown['value']) }}">
                </select>
                <button
                    type="button"
                    class="btn btn-outline-secondary manage-dropdown-btn"
                    data-manage-api="{{ $dropdown['api'] }}"
                    data-manage-field="{{ $dropdown['field'] }}"
                    data-manage-title="{{ $dropdown['title'] }}">
                    ⚙️
                </button>
            </div>
        </div>
    @endforeach
<div class="mb-3">
    <label>Mức độ ưu tiên:</label>
    <select name="priority" class="form-control">
        <option value="Khẩn cấp" {{ $task->priority === 'Khẩn cấp' ? 'selected' : '' }}>Khẩn cấp</option>
        <option value="Cao" {{ $task->priority === 'Cao' ? 'selected' : '' }}>Cao</option>
        <option value="Trung bình" {{ $task->priority === 'Trung bình' ? 'selected' : '' }}>Trung bình</option>
        <option value="Thấp" {{ $task->priority === 'Thấp' ? 'selected' : '' }}>Thấp</option>
    </select>
</div>

    <div class="mb-3">
        <label>Tiến độ:</label>
        <input type="number" name="progress" class="form-control" value="{{ $task->progress }}">
    </div>

    <div class="mb-3">
        <label>Chi tiết:</label>
        <textarea name="detail" class="form-control" rows="2">{{ $task->detail }}</textarea>
    </div>

    <div class="mb-3">
        <label>File link (cách nhau bằng dấu phẩy ,):</label>
        <input type="text" name="file_link" class="form-control" value="{{ $task->file_link }}">
    </div>

    <button type="submit" class="btn btn-primary">Cập nhật</button>
</form>

<!-- Modal quản lý dùng chung -->
<div class="modal fade" id="manageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manageModalTitle">Quản lý</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="manageModalBody">
                <!-- JS sẽ load -->
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
(function () {
    const csrfToken = '{{ csrf_token() }}';
    const SELECT2_OPTIONS = { tags: true, placeholder: 'Chọn hoặc nhập...', width: '100%' };

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('select[data-api]').forEach(initDropdown);
        document.querySelectorAll('.manage-dropdown-btn').forEach((button) => {
            button.addEventListener('click', () => {
                openManageModal(button.dataset.manageApi, button.dataset.manageField, button.dataset.manageTitle);
            });
        });
    });

    async function initDropdown(element) {
        const select = $('#' + element.id);
        const apiUrl = element.dataset.api;
        const fieldName = element.dataset.field;
        const selectedValue = element.dataset.selected || '';

        async function loadOptions() {
            try {
                const data = await fetchJson(apiUrl);
                select.empty();
                data.forEach((item) => select.append(new Option(item[fieldName], item[fieldName])));
                if (!select.hasClass('select2-hidden-accessible')) {
                    select.select2(SELECT2_OPTIONS);
                }
                if (selectedValue) {
                    select.val(selectedValue).trigger('change');
                }
            } catch (error) {
                alert(error.message || 'Lỗi khi tải dữ liệu!');
            }
        }

        select.off('select2:select').on('select2:select', async (e) => {
            const value = e.params.data.id;
            const exists = Array.from(select[0].options).some((opt) => opt.value === value);
            if (!exists && confirm(`Thêm mới: "${value}"?`)) {
                try {
                    await fetchJson(apiUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({ [fieldName]: value })
                    });
                    await loadOptions();
                    alert('Đã thêm!');
                } catch (error) {
                    alert(error.message || 'Không thể thêm mục mới.');
                }
            }
        });

        await loadOptions();
    }

    function openManageModal(apiUrl, fieldName, title) {
        document.getElementById('manageModalTitle').textContent = title;
        const body = document.getElementById('manageModalBody');
        body.innerHTML = 'Đang tải...';

        fetchJson(apiUrl)
            .then((data) => {
                if (!data.length) {
                    body.innerHTML = '<p class="text-muted mb-0">Chưa có dữ liệu.</p>';
                    return;
                }

                body.innerHTML = '';
                data.forEach((item) => {
                    const wrapper = document.createElement('div');
                    wrapper.classList.add('d-flex', 'align-items-center', 'mb-2');

                    const input = document.createElement('input');
                    input.type = 'text';
                    input.className = 'form-control me-2';
                    input.value = item[fieldName] || '';
                    input.addEventListener('change', () => updateItem(apiUrl, item.id, fieldName, input.value));

                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn btn-danger btn-sm';
                    button.textContent = 'Xóa';
                    button.addEventListener('click', () => deleteItem(apiUrl, item.id));

                    wrapper.appendChild(input);
                    wrapper.appendChild(button);
                    body.appendChild(wrapper);
                });
            })
            .catch((error) => {
                body.innerHTML = `<div class="text-danger">${error.message || 'Lỗi khi tải dữ liệu!'}</div>`;
            });

        new bootstrap.Modal(document.getElementById('manageModal')).show();
    }

    async function updateItem(apiUrl, id, fieldName, value) {
        try {
            await safeFetch(`${apiUrl}/${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ [fieldName]: value })
            });
            alert('Đã cập nhật!');
        } catch (error) {
            alert(error.message || 'Cập nhật thất bại!');
        }
    }

    async function deleteItem(apiUrl, id) {
        if (!confirm('Xóa mục này?')) return;
        try {
            await safeFetch(`${apiUrl}/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken }
            });
            alert('Đã xoá!');
        } catch (error) {
            alert(error.message || 'Không thể xoá mục này.');
        }
    }

    async function safeFetch(url, options = {}) {
        const response = await fetch(url, options);
        if (!response.ok) {
            const detail = await response.text().catch(() => '');
            console.error('API error', response.status, detail);
            throw new Error(response.status === 419 ? 'Phiên làm việc đã hết hạn, vui lòng tải lại trang.' : 'Yêu cầu thất bại, thử lại sau.');
        }
        return response;
    }

    async function fetchJson(url, options = {}) {
        const response = await safeFetch(url, options);
        try {
            return await response.json();
        } catch (error) {
            console.error('Invalid JSON response', error);
            throw new Error('Phản hồi từ máy chủ không hợp lệ.');
        }
    }
})();
</script>
@endsection
