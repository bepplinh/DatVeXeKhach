import React, { useState, useEffect, useMemo, useRef, useCallback } from "react";
import { paymentService } from "../../../services/paymentService";
import { transactionService } from "../../../services/admin/transactionService";
import { toast } from "react-toastify";
import PaymentDataGrid from "./components/PaymentDataGrid";
import RefundHistoryDataGrid from "./components/RefundHistoryDataGrid";
import ModificationHistoryDataGrid from "./components/ModificationHistoryDataGrid";
import PaymentFilters from "./components/PaymentFilters";
import PaymentDetailModal from "./components/PaymentDetailModal";
import CircularIndeterminate from "../../../components/Loading/Loading";
import { CreditCard, TrendingUp, DollarSign, CheckCircle } from "lucide-react";
import "./PaymentManagement.scss";

const PaymentManagement = () => {
    const [activeTab, setActiveTab] = useState("payments"); // 'payments', 'refunds', 'modifications'

    // Data States
    const [payments, setPayments] = useState([]);
    const [refunds, setRefunds] = useState([]);
    const [modifications, setModifications] = useState([]);

    // Loading State
    const [loading, setLoading] = useState(false);

    // Filters
    const [filters, setFilters] = useState({
        provider: null,
        from_date: null,
        to_date: null,
        booking_code: null,
    });
    const [searchQuery, setSearchQuery] = useState("");

    // Selected Detail
    const [selectedPayment, setSelectedPayment] = useState(null);

    // Pagination (Simple page state, backend pagination data handled implicitly for now simply)
    const [currentPage, setCurrentPage] = useState(1);

    // Stats
    const [stats, setStats] = useState({
        total: 0,
        totalAmount: 0,
        successCount: 0,
        successAmount: 0,
    });

    const isMountedRef = useRef(true);
    const fetchingRef = useRef(false);

    // --- Fetching Logic ---

    const fetchPayments = useCallback(async () => {
        try {
            setLoading(true);
            const params = {
                all: true,
                ...filters,
                search: searchQuery.trim() || undefined
            };
            // Clean undefined
            Object.keys(params).forEach(key => params[key] === undefined && delete params[key]);

            const response = await paymentService.getPayments(params);
            const data = response?.data || response;
            if (isMountedRef.current) {
                setPayments(Array.isArray(data) ? data : (data?.data || []));
            }
        } catch (error) {
            console.error("Error fetching payments:", error);
            if (isMountedRef.current) toast.error("Lỗi tải danh sách thanh toán");
        } finally {
            if (isMountedRef.current) setLoading(false);
        }
    }, [filters, searchQuery]);

    const fetchRefunds = useCallback(async () => {
        try {
            setLoading(true);
            const params = {
                from_date: filters.from_date,
                to_date: filters.to_date,
                // Search param mapped to booking_code filter if needed, or separate search
            };
            if (searchQuery.trim()) {
                // Currently backend search for refunds not fully implemented with explicit search param, 
                // but we can try passing it if we update backend or rely on date filters.
                // For now let's just use date filters for refunds as per current robust implementation
            }

            const response = await transactionService.getRefunds(params);
            if (isMountedRef.current) {
                setRefunds(response.data || []);
            }
        } catch (error) {
            console.error("Error fetching refunds:", error);
            if (isMountedRef.current) toast.error("Lỗi tải lịch sử hoàn tiền");
        } finally {
            if (isMountedRef.current) setLoading(false);
        }
    }, [filters, searchQuery]);

    const fetchModifications = useCallback(async () => {
        try {
            setLoading(true);
            const params = {
                search: searchQuery.trim() || undefined
            };
            const response = await transactionService.getModifications(params);
            if (isMountedRef.current) {
                setModifications(response.data || []);
            }
        } catch (error) {
            console.error("Error fetching modifications:", error);
            if (isMountedRef.current) toast.error("Lỗi tải lịch sử thay đổi");
        } finally {
            if (isMountedRef.current) setLoading(false);
        }
    }, [searchQuery]);

    const fetchStats = useCallback(async () => {
        try {
            const params = {};
            if (filters.from_date) params.from_date = filters.from_date;
            if (filters.to_date) params.to_date = filters.to_date;

            const response = await paymentService.getPaymentStats(params);
            const data = response?.data || response;

            if (isMountedRef.current) {
                setStats({
                    total: data?.total || 0,
                    totalAmount: data?.total_amount || 0,
                    successCount: data?.success_count || 0,
                    successAmount: data?.success_amount || 0,
                });
            }
        } catch (error) {
            console.error("Error fetching stats:", error);
        }
    }, [filters.from_date, filters.to_date]);

    // --- Effects ---

    useEffect(() => {
        isMountedRef.current = true;
        // Fetch stats always (or when filters change)
        fetchStats();

        // Fetch grid data based on active Tab
        if (activeTab === 'payments') {
            fetchPayments();
        } else if (activeTab === 'refunds') {
            fetchRefunds();
        } else if (activeTab === 'modifications') {
            fetchModifications();
        }

        return () => {
            isMountedRef.current = false;
        };
    }, [activeTab, fetchPayments, fetchRefunds, fetchModifications, fetchStats]);


    // --- Handlers ---

    const handleFilterChange = (newFilters) => {
        setFilters(newFilters);
        setCurrentPage(1);
    };

    const handleResetFilters = () => {
        setFilters({
            provider: null,
            from_date: null,
            to_date: null,
            booking_code: null,
        });
        setSearchQuery("");
        setCurrentPage(1);
    };

    const handleViewPayment = async (input) => {
        // If input is ID or object
        const id = input.id || input;
        // Note: For refunds/modifications composite IDs, we might need to parse original payment ID
        // But for "View Detail", we usually view the PARENT payment.

        // If ID contains underscore (composite), split it
        let paymentId = id;
        if (String(id).includes('_')) {
            paymentId = String(id).split('_')[0];
        }

        try {
            const response = await paymentService.getPaymentById(paymentId);
            const paymentData = response?.data || response;
            setSelectedPayment(paymentData);
        } catch (error) {
            console.error("Error fetching payment details:", error);
            toast.error("Không thể tải chi tiết thanh toán.");
        }
    };

    const formatCurrency = (amount) => {
        return new Intl.NumberFormat("vi-VN", {
            style: "currency",
            currency: "VND",
        }).format(amount || 0);
    };

    const statsCards = useMemo(
        () => [
            {
                label: "Tổng số giao dịch",
                value: stats.total,
                icon: CreditCard,
                accent: "primary",
            },
            {
                label: "Tổng doanh thu",
                value: formatCurrency(stats.totalAmount),
                icon: DollarSign,
                accent: "success",
            },
            {
                label: "Giao dịch thành công",
                value: stats.successCount,
                icon: CheckCircle,
                accent: "success",
            },
            {
                label: "Doanh thu thành công",
                value: formatCurrency(stats.successAmount),
                icon: TrendingUp,
                accent: "info",
            },
        ],
        [stats]
    );

    // --- Render Helpers ---

    const renderContent = () => {
        if (loading) {
            return (
                <div className="payment-management__loading">
                    <CircularIndeterminate />
                </div>
            );
        }

        if (activeTab === 'payments') {
            if (payments.length === 0) return <EmptyState />;
            return (
                <PaymentDataGrid
                    payments={payments}
                    onView={handleViewPayment}
                    loading={loading}
                />
            );
        }

        if (activeTab === 'refunds') {
            if (refunds.length === 0) return <EmptyState />;
            return (
                <RefundHistoryDataGrid
                    refunds={refunds}
                    loading={loading}
                />
            );
        }

        if (activeTab === 'modifications') {
            if (modifications.length === 0) return <EmptyState />;
            return (
                <ModificationHistoryDataGrid
                    transactions={modifications}
                    loading={loading}
                />
            );
        }
    };

    const EmptyState = () => (
        <div className="payment-management__empty">
            <p>Không tìm thấy dữ liệu nào.</p>
            <button
                type="button"
                className="payment-management__reset-btn"
                onClick={handleResetFilters}
            >
                Reset bộ lọc
            </button>
        </div>
    );

    return (
        <div className="payment-management">
            <div className="payment-management__container">
                <div className="payment-management__header">
                    <div>
                        <h1 className="payment-management__title">
                            Quản lý thanh toán
                        </h1>
                        <p className="payment-management__subtitle">
                            Theo dõi và quản lý tất cả các giao dịch và hoàn tiền
                        </p>
                    </div>
                </div>

                <div className="payment-management__stats">
                    {statsCards.map((stat, index) => {
                        const Icon = stat.icon;
                        return (
                            <div
                                key={index}
                                className={`payment-management__stat payment-management__stat--${stat.accent}`}
                            >
                                <div className="payment-management__stat-icon">
                                    <Icon size={24} />
                                </div>
                                <div className="payment-management__stat-content">
                                    <span className="payment-management__stat-label">
                                        {stat.label}
                                    </span>
                                    <span className="payment-management__stat-value">
                                        {stat.value}
                                    </span>
                                </div>
                            </div>
                        );
                    })}
                </div>

                <div className="payment-management__tabs">
                    <button
                        className={activeTab === 'payments' ? 'active' : ''}
                        onClick={() => setActiveTab('payments')}
                    >
                        Giao dịch thanh toán
                    </button>
                    <button
                        className={activeTab === 'refunds' ? 'active' : ''}
                        onClick={() => setActiveTab('refunds')}
                    >
                        Lịch sử Hoàn tiền
                    </button>
                    <button
                        className={activeTab === 'modifications' ? 'active' : ''}
                        onClick={() => setActiveTab('modifications')}
                    >
                        Lịch sử Đổi vé/Chuyến
                    </button>
                </div>

                {activeTab === 'payments' && (
                    <PaymentFilters
                        filters={filters}
                        onFilterChange={handleFilterChange}
                        onReset={handleResetFilters}
                        searchQuery={searchQuery}
                        onSearchChange={setSearchQuery}
                    />
                )}

                {/* 
                    Note: Filters for Refunds/Modifications could be separate or shared.
                    For simplicity, basic filters like search are shared but date/provider 
                    might be less relevant or handled differently. 
                    Currently PaymentFilters component is quite specific to Payments.
                    We could conditionally render filters valid for current tab.
                */}
                {(activeTab === 'refunds' || activeTab === 'modifications') && (
                    <div style={{ marginBottom: 20, display: 'flex', gap: 10 }}>
                        {/* Simple Search Input if needed as per PaymentFilters logic */}
                        <div className="search-box" style={{ flex: 1, maxWidth: 400 }}>
                            <div className="search-input-wrapper" style={{ display: 'flex', alignItems: 'center', background: 'white', padding: '8px 12px', borderRadius: 8, border: '1px solid #dee2e6' }}>
                                <input
                                    type="text"
                                    placeholder="Tìm theo mã vé..."
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                    style={{ border: 'none', outline: 'none', width: '100%', fontSize: 14 }}
                                />
                            </div>
                        </div>
                    </div>
                )}

                <div className="payment-management__content">
                    {renderContent()}
                </div>
            </div>

            {selectedPayment && (
                <PaymentDetailModal
                    payment={selectedPayment}
                    onClose={() => setSelectedPayment(null)}
                    onRefundSuccess={() => {
                        fetchPayments();
                        fetchStats();
                        if (activeTab === 'refunds') fetchRefunds();
                        if (activeTab === 'modifications') fetchModifications();
                    }}
                />
            )}
        </div>
    );
};

export default PaymentManagement;

