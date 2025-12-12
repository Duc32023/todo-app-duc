import React, { useCallback, useEffect, useState } from "react";
import Swal from "sweetalert2";

export default function UsersTab() {
  const [users, setUsers] = useState([]);
  const [form, setForm] = useState({
    name: "",
    email: "",
    password: "",
    old_password: "",
    role: "Nhân viên",
    department_id: "",
  });
  const [editing, setEditing] = useState(null);
  const [departments, setDepartments] = useState([]);

  const loadDepartments = useCallback(() => {
    fetch("/api/departments")
      .then((res) => res.json())
      .then((data) => setDepartments(Array.isArray(data) ? data : []))
      .catch(() => setDepartments([]));
  }, []);

  const csrf =
    document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ||
    "";

  // ============== LOAD USERS ==============
  useEffect(() => {
    fetch("/management/users", { headers: { Accept: "application/json" } })
      .then((res) => res.json())
      .then(setUsers)
      .catch(() => Swal.fire("Lỗi", "Không tải được danh sách user", "error"));
  }, []);

  useEffect(() => {
    loadDepartments();
  }, [loadDepartments]);

  // ============== RESET FORM ==============
  const resetForm = () => {
    setForm({
      name: "",
      email: "",
      password: "",
      old_password: "",
      role: "Nhân viên",
      department_id: "",
    });
    setEditing(null);
  };

  // ============== SAVE USER ==============
  const handleSave = async () => {
    const isEdit = !!editing;
    const url = isEdit ? `/management/users/${editing.id}` : "/management/users";
    const payload = { ...form };
    if (!payload.department_id) {
      payload.department_id = null;
    }

    // Nếu edit, dùng POST + _method=PUT
    const method = "POST";
    if (isEdit) payload._method = "PUT";

    try {
      const res = await fetch(url, {
        method,
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrf,
          Accept: "application/json",
        },
        body: JSON.stringify(payload),
      });

      const data = await res.json();

      if (!res.ok) {
        if (data?.error) {
          Swal.fire("Lỗi", data.error, "error");
        } else {
          Swal.fire("Lỗi", "Không thể lưu người dùng", "error");
        }
        return;
      }

      if (isEdit) {
        setUsers((prev) => prev.map((u) => (u.id === data.id ? data : u)));
        Swal.fire("Thành công", "Đã cập nhật người dùng", "success");
      } else {
        setUsers((prev) => [data, ...prev]);
        Swal.fire("Thành công", "Đã thêm người dùng", "success");
      }

      loadDepartments();
      resetForm();
    } catch (err) {
      Swal.fire("Lỗi", "Không thể lưu người dùng", "error");
    }
  };

  // ============== EDIT USER ==============
  const handleEdit = (user) => {
    setForm({
      name: user.name,
      email: user.email,
      password: "",
      old_password: "",
      role: user.role || "Nhân viên",
      department_id: user.department_id ? String(user.department_id) : "",
    });
    setEditing(user);
  };

  // ============== DELETE USER ==============
  const handleDelete = async (id) => {
    const confirm = await Swal.fire({
      title: "Bạn có chắc chắn muốn xóa?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: "Xóa",
    });
    if (!confirm.isConfirmed) return;

    try {
      const res = await fetch(`/management/users/${id}`, {
        method: "DELETE",
        headers: { "X-CSRF-TOKEN": csrf },
      });
      if (!res.ok) throw new Error();
      setUsers((prev) => prev.filter((u) => u.id !== id));
      Swal.fire("Thành công", "Đã xóa người dùng", "success");
    } catch {
      Swal.fire("Lỗi", "Không thể xóa người dùng", "error");
    }
  };

  const isManagerRole = form.role === "Trưởng phòng";

  return (
    <div className="p-3">
      <h3 className="fw-bold mb-3">Quản lý người dùng</h3>

      {/* FORM NHẬP */}
      <div className="card p-3 mb-4 shadow-sm">
        <div className="row g-2">
          <div className="col-md-4">
            <input
              type="text"
              className="form-control"
              placeholder="Tên"
              value={form.name}
              onChange={(e) => setForm({ ...form, name: e.target.value })}
            />
          </div>
          <div className="col-md-4">
            <input
              type="email"
              className="form-control"
              placeholder="Email"
              value={form.email}
              onChange={(e) => setForm({ ...form, email: e.target.value })}
            />
          </div>
          <div className="col-md-4">
            <select
              className="form-select"
              value={form.role}
              onChange={(e) => {
                const nextRole = e.target.value;
                setForm((prev) => ({
                  ...prev,
                  role: nextRole,
                  department_id: nextRole === "Trưởng phòng" ? "" : prev.department_id,
                }));
              }}
            >
              <option value="Nhân viên">Nhân viên</option>
              <option value="Trưởng phòng">Trưởng phòng</option>
              <option value="Admin">Admin</option>
            </select>
          </div>
          <div className="col-md-4">
            <select
              className="form-select"
              value={form.department_id}
              onChange={(e) => setForm({ ...form, department_id: e.target.value })}
              disabled={isManagerRole}
            >
              <option value="">Chưa gán phòng</option>
              {departments.map((dept) => (
                <option key={dept.id} value={dept.id}>
                  {dept.name}
                </option>
              ))}
            </select>
            {isManagerRole && (
              <small className="text-muted">
                Trưởng phòng sẽ tự động có lựa chọn trùng với tên của họ.
              </small>
            )}
          </div>
          {/* Chỉ hiện nhập mật khẩu cũ nếu đang sửa và user đang đăng nhập KHÔNG phải Admin */}
          {editing && window.currentUserRole !== "Admin" && (
            <div className="col-md-6">
              <input
                type="password"
                className="form-control"
                placeholder="Nhập mật khẩu cũ (nếu đổi)"
                value={form.old_password}
                onChange={(e) =>
                  setForm({ ...form, old_password: e.target.value })
                }
              />
            </div>
          )}

          <div className="col-md-6">
            <input
              type="password"
              className="form-control"
              placeholder={editing ? "Mật khẩu mới (nếu đổi)" : "Mật khẩu"}
              value={form.password}
              onChange={(e) => setForm({ ...form, password: e.target.value })}
            />
          </div>



          <div className="col-md-6">
            <button className="btn btn-primary w-100" onClick={handleSave}>
              {editing ? "Cập nhật" : "Thêm"}
            </button>
            {editing && (
              <button
                className="btn btn-secondary mt-2 w-100"
                onClick={resetForm}
              >
                Hủy
              </button>
            )}
          </div>
        </div>
      </div>

      {/* BẢNG NGƯỜI DÙNG */}
      <table className="table table-bordered align-middle">
        <thead className="table-light">
          <tr>
            <th>#</th>
            <th>Tên</th>
            <th>Email</th>
            <th>Vai trò</th>
            <th>Phòng / Trưởng phòng</th>
            <th>Hành động</th>
          </tr>
        </thead>
        <tbody>
          {users.length === 0 ? (
            <tr>
              <td colSpan="6" className="text-center text-muted py-4">
                Không có người dùng
              </td>
            </tr>
          ) : (
            users.map((u, i) => (
              <tr key={u.id}>
                <td>{i + 1}</td>
                <td>{u.name}</td>
                <td>{u.email}</td>
                <td>{u.role || "Nhân viên"}</td>
                <td>{u.department?.name || "—"}</td>
                <td>
                  <button
                    className="btn btn-warning btn-sm me-2"
                    onClick={() => handleEdit(u)}
                  >
                    Sửa
                  </button>
                  <button
                    className="btn btn-danger btn-sm"
                    onClick={() => handleDelete(u.id)}
                  >
                    Xóa
                  </button>
                </td>
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  );
}
