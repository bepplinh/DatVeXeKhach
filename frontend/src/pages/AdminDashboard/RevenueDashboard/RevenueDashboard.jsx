import { useState, useEffect, useCallback } from "react";
import { revenueService } from "../../../services/admin/revenueService";
import { toast } from "react-toastify";
import {
    Loader2,
    TrendingUp,
    Activity,
    Map as MapIcon,
    PieChart,
    BarChart3
} from "lucide-react";
import dayjs from "dayjs";
import "./RevenueDashboard.scss";

// Components
import RevenueStats from "./components/RevenueStats";
import RevenueFilters from "./components/RevenueFilters";
import ChartCard from "./components/ChartCard";
import RevenueTrendChart from "./components/RevenueTrendChart";
import RevenueBookingChart from "./components/RevenueBookingChart";
import TopRoutesBarChart from "./components/TopRoutesBarChart";
import TopRoutesPieChart from "./components/TopRoutesPieChart";
import TopTripsTable from "./components/TopTripsTable";
import TopCustomersCardList from "./components/TopCustomersCardList";

function RevenueDashboard() {
    const [loading, setLoading] = useState(true);
    const [dashboardData, setDashboardData] = useState(null);
    const [trendData, setTrendData] = useState([]);
    const [topRoutes, setTopRoutes] = useState([]);
    const [topTrips, setTopTrips] = useState([]);
    const [topCustomers, setTopCustomers] = useState([]);

    // Filters
    const [period, setPeriod] = useState("day");
    const [date, setDate] = useState(dayjs().format("YYYY-MM-DD"));
    const [fromDate, setFromDate] = useState(
        dayjs().subtract(30, "day").format("YYYY-MM-DD")
    );
    const [toDate, setToDate] = useState(dayjs().format("YYYY-MM-DD"));

    // Load dashboard data
    const loadDashboard = useCallback(async () => {
        try {
            setLoading(true);
            const response = await revenueService.getDashboard({ period, date });
            if (response.success) {
                setDashboardData(response.data);
            }
        } catch (error) {
            console.error("Error loading dashboard:", error);
            toast.error("Không thể tải dữ liệu dashboard");
        } finally {
            setLoading(false);
        }
    }, [period, date]);

    // Load trend data
    const loadTrend = useCallback(async () => {
        try {
            const response = await revenueService.getTrend({
                period,
                from_date: fromDate,
                to_date: toDate,
            });
            if (response.success) {
                setTrendData(response.data.trend || []);
            }
        } catch (error) {
            console.error("Error loading trend:", error);
            toast.error("Không thể tải dữ liệu xu hướng");
        }
    }, [period, fromDate, toDate]);

    // Load top routes
    const loadTopRoutes = useCallback(async () => {
        try {
            const response = await revenueService.getTopRoutes({
                limit: 10,
                from_date: fromDate,
                to_date: toDate,
            });
            if (response.success) {
                setTopRoutes(response.data.top_routes || []);
            }
        } catch (error) {
            console.error("Error loading top routes:", error);
            toast.error("Không thể tải top tuyến đường");
        }
    }, [fromDate, toDate]);

    // Load top trips
    const loadTopTrips = useCallback(async () => {
        try {
            const response = await revenueService.getTopTrips({
                limit: 10,
                from_date: fromDate,
                to_date: toDate,
            });
            if (response.success) {
                setTopTrips(response.data.top_trips || []);
            }
        } catch (error) {
            console.error("Error loading top trips:", error);
            toast.error("Không thể tải top chuyến xe");
        }
    }, [fromDate, toDate]);

    // Load top customers
    const loadTopCustomers = useCallback(async () => {
        try {
            const response = await revenueService.getTopCustomers({
                limit: 10,
                from_date: fromDate,
                to_date: toDate,
            });
            if (response.success) {
                setTopCustomers(response.data.top_customers || []);
            }
        } catch (error) {
            console.error("Error loading top customers:", error);
            toast.error("Không thể tải top khách hàng");
        }
    }, [fromDate, toDate]);

    useEffect(() => {
        loadDashboard();
    }, [loadDashboard]);

    useEffect(() => {
        loadTrend();
        loadTopRoutes();
        loadTopTrips();
        loadTopCustomers();
    }, [loadTrend, loadTopRoutes, loadTopTrips, loadTopCustomers]);

    const handlePeriodChange = (newPeriod) => {
        setPeriod(newPeriod);
        // Auto adjust date range based on period
        const today = dayjs();
        let newFromDate = fromDate;
        let newToDate = toDate;

        switch (newPeriod) {
            case "day":
                newFromDate = today.subtract(30, "day").format("YYYY-MM-DD");
                newToDate = today.format("YYYY-MM-DD");
                break;
            case "week":
                newFromDate = today.subtract(12, "week").format("YYYY-MM-DD");
                newToDate = today.format("YYYY-MM-DD");
                break;
            case "month":
                newFromDate = today.subtract(12, "month").format("YYYY-MM-DD");
                newToDate = today.format("YYYY-MM-DD");
                break;
            case "quarter":
                newFromDate = today.subtract(4, "quarter").format("YYYY-MM-DD");
                newToDate = today.format("YYYY-MM-DD");
                break;
            case "year":
                newFromDate = today.subtract(5, "year").format("YYYY-MM-DD");
                newToDate = today.format("YYYY-MM-DD");
                break;
        }

        setFromDate(newFromDate);
        setToDate(newToDate);
    };

    if (loading && !dashboardData) {
        return (
            <div className="revenue-dashboard revenue-dashboard--loading">
                <Loader2 className="revenue-dashboard__loader" size={48} />
                <p>Đang tải dữ liệu...</p>
            </div>
        );
    }

    return (
        <div className="revenue-dashboard">
            {/* Header */}
            <div className="revenue-dashboard__header">
                <div>
                    <h1 className="revenue-dashboard__title">
                        Báo cáo Doanh thu
                    </h1>
                    <p className="revenue-dashboard__subtitle">
                        Theo dõi và phân tích doanh thu hệ thống
                    </p>
                </div>
                <RevenueFilters
                    period={period}
                    date={date}
                    fromDate={fromDate}
                    toDate={toDate}
                    onPeriodChange={handlePeriodChange}
                    onDateChange={setDate}
                    onFromDateChange={setFromDate}
                    onToDateChange={setToDate}
                    showDateRange={false}
                />
            </div>

            {/* Dashboard Stats */}
            <RevenueStats dashboardData={dashboardData} period={period} />

            {/* Check if filter bar should be wrapped or styled differently */}
            <div className="revenue-dashboard__filter-bar">
                <RevenueFilters
                    period={period}
                    date={date}
                    fromDate={fromDate}
                    toDate={toDate}
                    onPeriodChange={handlePeriodChange}
                    onDateChange={setDate}
                    onFromDateChange={setFromDate}
                    onToDateChange={setToDate}
                    showDateRange={true}
                />
            </div>

            {/* Charts Grid */}
            <div className="revenue-dashboard__charts">
                <div className="revenue-dashboard__chart-card">
                    <div className="revenue-dashboard__chart-header">
                        <div className="revenue-dashboard__chart-icon">
                            <TrendingUp size={20} />
                        </div>
                        <div>
                            <h3 className="revenue-dashboard__chart-title">Xu hướng Doanh thu</h3>
                            <p className="revenue-dashboard__chart-subtitle">Biểu đồ doanh thu theo thời gian</p>
                        </div>
                    </div>
                    <div className="revenue-dashboard__chart-body">
                        <RevenueTrendChart data={trendData} />
                    </div>
                </div>

                <div className="revenue-dashboard__chart-card">
                    <div className="revenue-dashboard__chart-header">
                        <div className="revenue-dashboard__chart-icon">
                            <Activity size={20} />
                        </div>
                        <div>
                            <h3 className="revenue-dashboard__chart-title">Số lượng Vé bán</h3>
                            <p className="revenue-dashboard__chart-subtitle">Biểu đồ số vé đã bán</p>
                        </div>
                    </div>
                    <div className="revenue-dashboard__chart-body">
                        <RevenueBookingChart data={trendData} />
                    </div>
                </div>

                <div className="revenue-dashboard__chart-card">
                    <div className="revenue-dashboard__chart-header">
                        <div className="revenue-dashboard__chart-icon">
                            <MapIcon size={20} />
                        </div>
                        <div>
                            <h3 className="revenue-dashboard__chart-title">Top Tuyến đường</h3>
                            <p className="revenue-dashboard__chart-subtitle">Doanh thu theo từng tuyến</p>
                        </div>
                    </div>
                    <div className="revenue-dashboard__chart-body">
                        <TopRoutesBarChart data={topRoutes} />
                    </div>
                </div>

                <div className="revenue-dashboard__chart-card">
                    <div className="revenue-dashboard__chart-header">
                        <div className="revenue-dashboard__chart-icon">
                            <PieChart size={20} />
                        </div>
                        <div>
                            <h3 className="revenue-dashboard__chart-title">Phân bổ Doanh thu</h3>
                            <p className="revenue-dashboard__chart-subtitle">Tỷ lệ doanh thu theo tuyến</p>
                        </div>
                    </div>
                    <div className="revenue-dashboard__chart-body">
                        <TopRoutesPieChart data={topRoutes} />
                    </div>
                </div>
            </div>

            {/* Top Trips Table */}
            <div className="revenue-dashboard__table-card">
                <div className="revenue-dashboard__chart-header">
                    <h3 className="revenue-dashboard__chart-title">
                        Top Chuyến xe
                    </h3>
                </div>
                <div className="revenue-dashboard__table-body">
                    <TopTripsTable data={topTrips} />
                </div>
            </div>

            {/* Top Customers Card List */}
            <div className="revenue-dashboard__table-card">
                <div className="revenue-dashboard__chart-header">
                    <h3 className="revenue-dashboard__chart-title">
                        🏆 Top Khách hàng đặt vé nhiều nhất
                    </h3>
                </div>
                <div className="revenue-dashboard__table-body">
                    <TopCustomersCardList data={topCustomers} />
                </div>
            </div>
        </div>
    );
}

export default RevenueDashboard;

