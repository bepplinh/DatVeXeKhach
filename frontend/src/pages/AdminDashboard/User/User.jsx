import React, {
    useState,
    useEffect,
    useMemo,
    useRef,
    useCallback,
} from "react";
import { userService } from "../../../services/userService";
import { toast } from "react-toastify";
import UserFilters from "./components/UserFilters";
import UserDetailModal from "./components/UserDetailModal";
import UserFormDialog from "./components/UserFormDialog";
import UserDataGrid from "./components/UserDataGrid";
import CircularIndeterminate from "../../../components/Loading/Loading";
import { UserPlus, Search } from "lucide-react";
import "./User.scss";

const User = () => {
    const [users, setUsers] = useState([]);
    const [filteredUsers, setFilteredUsers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [filters, setFilters] = useState({
        status: "",
        role: "",
        from_date: "",
        to_date: "",
    });
    const [searchQuery, setSearchQuery] = useState("");
    const [sortOption, setSortOption] = useState("newest");
    const [activeQuickFilter, setActiveQuickFilter] = useState("all");
    const [selectedUser, setSelectedUser] = useState(null);
    const [showUserForm, setShowUserForm] = useState(false);
    const [editingUser, setEditingUser] = useState(null);
    const [formLoading, setFormLoading] = useState(false);
    const isMountedRef = useRef(true);
    const hasFetchedRef = useRef(false);
    const fetchingRef = useRef(false);

    const fetchUsers = useCallback(async () => {
        // Tránh gọi API nhiều lần cùng lúc
        if (fetchingRef.current) {
            return;
        }

        fetchingRef.current = true;

        try {
            setLoading(true);
            const response = await userService.getUsers({
                per_page: 100,
                page: 1,
            });

            // Chỉ update state nếu component vẫn còn mount
            if (isMountedRef.current) {
                const usersData = response?.data?.data || response?.data || [];
                setUsers(usersData);
                setFilteredUsers(usersData);
            }
        } catch (error) {
            console.error("Error fetching users:", error);
            if (isMountedRef.current) {
                toast.error(
                    "Không thể tải danh sách người dùng. Vui lòng thử lại!"
                );
            }
        } finally {
            fetchingRef.current = false;
            if (isMountedRef.current) {
                setLoading(false);
            }
        }
    }, []);

    useEffect(() => {
        isMountedRef.current = true;

        // Chỉ gọi API một lần khi component mount
        // Kiểm tra cả hasFetchedRef và fetchingRef để tránh gọi trùng
        if (!hasFetchedRef.current && !fetchingRef.current) {
            hasFetchedRef.current = true;
            fetchUsers();
        }

        return () => {
            isMountedRef.current = false;
        };
    }, [fetchUsers]);

    useEffect(() => {
        applyFilters();
    }, [users, filters, searchQuery, sortOption]);

    // Đồng bộ selectedUser với dữ liệu mới sau khi fetch lại danh sách users
    useEffect(() => {
        if (selectedUser) {
            const updatedUser = users.find((u) => u.id === selectedUser.id);
            if (updatedUser) {
                // Cập nhật selectedUser với dữ liệu mới từ danh sách
                setSelectedUser(updatedUser);
            }
        }
    }, [users]);

    const stats = useMemo(() => {
        const total = users.length;
        const active = users.filter(
            (user) => (user.status || "active") === "active"
        ).length;
        const newUsers = users.filter((user) => {
            const createdDate = new Date(user.created_at || user.createdAt);
            const now = new Date();
            const diffTime = now - createdDate;
            const diffDays = diffTime / (1000 * 60 * 60 * 24);
            return diffDays <= 30;
        }).length;
        const admins = users.filter((user) => user.role === "admin").length;

        return [
            {
                label: "Tổng số user",
                value: total,
                accent: "primary",
            },
            {
                label: "Đang hoạt động",
                value: active,
                accent: "success",
            },
            {
                label: "Mới đăng ký (30 ngày)",
                value: newUsers,
                accent: "info",
            },
            {
                label: "Quản trị viên",
                value: admins,
                accent: "warning",
            },
        ];
    }, [users]);

    const applyFilters = () => {
        let filtered = [...users];

        // Lọc theo trạng thái
        if (filters.status !== "") {
            filtered = filtered.filter((user) => {
                const userStatus = user.status || "active";
                return userStatus === filters.status;
            });
        }

        // Lọc theo vai trò
        if (filters.role !== "") {
            filtered = filtered.filter((user) => user.role === filters.role);
        }

        // Lọc theo ngày đăng ký
        if (filters.from_date !== "") {
            filtered = filtered.filter((user) => {
                const userDate = new Date(user.created_at || user.createdAt);
                const fromDate = new Date(filters.from_date);
                return userDate >= fromDate;
            });
        }

        if (filters.to_date !== "") {
            filtered = filtered.filter((user) => {
                const userDate = new Date(user.created_at || user.createdAt);
                const toDate = new Date(filters.to_date);
                toDate.setHours(23, 59, 59, 999);
                return userDate <= toDate;
            });
        }

        // Tìm kiếm theo tên, email, số điện thoại
        if (searchQuery.trim() !== "") {
            const keyword = searchQuery.trim().toLowerCase();
            filtered = filtered.filter((user) => {
                const searchText = [user.name, user.email, user.phone]
                    .filter(Boolean)
                    .join(" ")
                    .toLowerCase();

                return searchText.includes(keyword);
            });
        }

        // Sắp xếp
        filtered.sort((a, b) => {
            if (sortOption === "name-asc") {
                return (a.name || "").localeCompare(b.name || "");
            }
            if (sortOption === "name-desc") {
                return (b.name || "").localeCompare(a.name || "");
            }

            const dateA = new Date(a.created_at || a.createdAt).getTime();
            const dateB = new Date(b.created_at || b.createdAt).getTime();

            if (sortOption === "oldest") {
                return dateA - dateB;
            }

            // default newest
            return dateB - dateA;
        });

        setFilteredUsers(filtered);
    };

    const handleFilterChange = (newFilters) => {
        setFilters(newFilters);
    };

    const handleResetFilters = () => {
        setFilters({
            status: "",
            role: "",
            from_date: "",
            to_date: "",
        });
        setSearchQuery("");
        setActiveQuickFilter("all");
        setSortOption("newest");
    };

    const quickFilters = [
        { key: "all", label: "Tất cả", status: "" },
        { key: "active", label: "Đang hoạt động", status: "active" },
        { key: "inactive", label: "Tạm ngưng", status: "inactive" },
        { key: "banned", label: "Đã khóa", status: "banned" },
        { key: "customer", label: "Khách hàng", role: "customer" },
        { key: "staff", label: "Nhân viên", role: "staff" },
        { key: "admin", label: "Quản trị viên", role: "admin" },
    ];

    const handleQuickFilter = (filter) => {
        setActiveQuickFilter(filter.key);
        setFilters((prev) => ({
            ...prev,
            status: filter.status || "",
            role: filter.role || "",
        }));
    };

    const handleEdit = (user) => {
        setEditingUser(user);
        setShowUserForm(true);
    };

    const handleDelete = async (userId) => {
        try {
            await userService.deleteUser(userId);
            toast.success("Xóa người dùng thành công!");
            // Reset flag và fetch lại
            hasFetchedRef.current = false;
            fetchingRef.current = false;
            fetchUsers();
        } catch (error) {
            console.error("Error deleting user:", error);
            toast.error("Không thể xóa người dùng. Vui lòng thử lại!");
        }
    };

    const handleFormSubmit = async (formData) => {
        try {
            setFormLoading(true);
            if (editingUser) {
                await userService.updateUser(editingUser.id, formData);
                toast.success("Cập nhật người dùng thành công!");
            } else {
                await userService.createUser(formData);
                toast.success("Tạo người dùng mới thành công!");
            }
            setShowUserForm(false);
            setEditingUser(null);
            // Reset flag và fetch lại
            hasFetchedRef.current = false;
            fetchingRef.current = false;
            fetchUsers();
        } catch (error) {
            console.error("Error saving user:", error);
            toast.error(
                editingUser
                    ? "Không thể cập nhật người dùng. Vui lòng thử lại!"
                    : "Không thể tạo người dùng. Vui lòng thử lại!"
            );
        } finally {
            setFormLoading(false);
        }
    };

    return (
        <div className="user-management">
            <div className="user-management__container">
                <div className="user-management__header">
                    <div>
                        <h1 className="user-management__title">
                            Quản lý người dùng
                        </h1>
                        <p className="user-management__subtitle">
                            Quản lý tất cả người dùng trong hệ thống của bạn
                        </p>
                    </div>
                    <button
                        className="user-management__add-btn"
                        onClick={() => {
                            setEditingUser(null);
                            setShowUserForm(true);
                        }}
                    >
                        <UserPlus size={20} />
                        <span>Thêm user mới</span>
                    </button>
                </div>

                <div className="user-management__stats">
                    {stats.map((item) => (
                        <div
                            key={item.label}
                            className={`user-management__stat user-management__stat--${item.accent}`}
                        >
                            <span className="user-management__stat-label">
                                {item.label}
                            </span>
                            <span className="user-management__stat-value">
                                {item.value}
                            </span>
                        </div>
                    ))}
                </div>

                <div className="user-management__actions">
                    <div className="user-management__actions-left">
                        <div className="user-management__search">
                            <label htmlFor="user-search">Tìm kiếm user</label>
                            <div className="user-management__search-input">
                                <Search size={18} />
                                <input
                                    id="user-search"
                                    type="text"
                                    placeholder="Nhập tên, email hoặc số điện thoại..."
                                    value={searchQuery}
                                    onChange={(e) =>
                                        setSearchQuery(e.target.value)
                                    }
                                />
                            </div>
                        </div>

                        <div className="user-management__quick-filters">
                            {quickFilters.map((filter) => (
                                <button
                                    key={filter.key}
                                    type="button"
                                    className={`user-management__chip ${activeQuickFilter === filter.key
                                        ? "user-management__chip--active"
                                        : ""
                                        }`}
                                    onClick={() => handleQuickFilter(filter)}
                                >
                                    {filter.label}
                                </button>
                            ))}
                        </div>

                        <div className="user-management__sort">
                            <label htmlFor="user-sort">Sắp xếp</label>
                            <select
                                id="user-sort"
                                value={sortOption}
                                onChange={(e) => setSortOption(e.target.value)}
                            >
                                <option value="newest">Mới nhất</option>
                                <option value="oldest">Cũ nhất</option>
                                <option value="name-asc">Tên A-Z</option>
                                <option value="name-desc">Tên Z-A</option>
                            </select>
                        </div>
                    </div>

                    <div className="user-management__actions-right">
                        <UserFilters
                            filters={filters}
                            onFilterChange={handleFilterChange}
                            onReset={handleResetFilters}
                        />
                    </div>
                </div>

                {/* Content Area */}
                <div className="user-management__content">
                    {loading ? (
                        <div className="user-management__loading">
                            <CircularIndeterminate />
                        </div>
                    ) : filteredUsers.length === 0 ? (
                        <div className="user-management__empty">
                            <p>
                                Không tìm thấy người dùng nào phù hợp với bộ
                                lọc của bạn.
                            </p>
                            <button
                                type="button"
                                className="user-management__reset-btn"
                                onClick={handleResetFilters}
                            >
                                Reset bộ lọc
                            </button>
                        </div>
                    ) : (
                        <UserDataGrid
                            users={filteredUsers}
                            onView={(user) => setSelectedUser(user)}
                            onEdit={handleEdit}
                            onDelete={handleDelete}
                        />
                    )}
                </div>
            </div>

            {selectedUser && (
                <UserDetailModal
                    user={selectedUser}
                    onClose={() => setSelectedUser(null)}
                    onEdit={handleEdit}
                />
            )}

            {showUserForm && (
                <UserFormDialog
                    open={showUserForm}
                    onClose={() => {
                        setShowUserForm(false);
                        setEditingUser(null);
                    }}
                    user={editingUser}
                    onSubmit={handleFormSubmit}
                    loading={formLoading}
                />
            )}
        </div>
    );
};

export default User;
