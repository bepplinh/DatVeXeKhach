import { useRef, useState, useEffect } from "react";
import { Loader2, RefreshCw } from "lucide-react";
import { revenueService } from "../../services/admin/revenueService";
import { adminBookingService } from "../../services/admin/bookingService";
import { adminTripService } from "../../services/admin/tripService";
import apiClient from "../../apis/axiosClient";
import "./AdminDashboard.scss";
import { formatRelativeTime } from "./utils";
import dayjs from "dayjs";

import DashboardStats from "./components/DashboardStats";
import RecentActivities from "./components/RecentActivities";
import QuickLinks from "./components/QuickLinks";
import DashboardFeatures from "./components/DashboardFeatures";
import LiveOperations from "./components/LiveOperations";
import OccupancyRate from "./components/OccupancyRate";

function AdminDashboard() {
    const [loading, setLoading] = useState(true);
    const [dashboardData, setDashboardData] = useState(null);
    const [trendData, setTrendData] = useState([]);
    const [recentActivities, setRecentActivities] = useState([]);
    const [activitiesLoading, setActivitiesLoading] = useState(true);

    const [upcomingTrips, setUpcomingTrips] = useState([]);
    const [occupancyRate, setOccupancyRate] = useState(0);

    const [error, setError] = useState(null);

    // Lấy dữ liệu dashboard
    const fetchDashboardData = async () => {
        try {
            setLoading(true);
            setError(null);

            const [statsRes, trendRes] = await Promise.all([
                revenueService.getDashboard({ period: "day" }),
                revenueService.getTrend({
                    period: "day",
                    from_date: dayjs().subtract(7, 'day').format('YYYY-MM-DD'),
                    to_date: dayjs().format('YYYY-MM-DD')
                })
            ]);

            setDashboardData(statsRes);
            if (trendRes?.success && trendRes?.data?.trend) {
                setTrendData(trendRes.data.trend);
            }
        } catch (err) {
            console.error("Failed to fetch dashboard data:", err);
            setError("Không thể tải dữ liệu. Vui lòng thử lại sau.");
        } finally {
            setLoading(false);
        }
    };

    const fetchWidgetsData = async () => {
        try {
            const tripsRes = await adminTripService.getTrips({
                status: 'scheduled',
                date_from: dayjs().format('YYYY-MM-DD'),
                limit: 20
            });

            const trips = tripsRes?.data?.data || [];

            const now = dayjs();
            const upcoming = trips.filter(trip => {
                const dep = dayjs(trip.departure_time);
                return dep.isAfter(now) && dep.diff(now, 'hour') <= 6;
            }).slice(0, 5);
            setUpcomingTrips(upcoming);

            if (trips.length > 0) {
                const totalOccupancy = trips.reduce((acc, trip) => {
                    const booked = trip.bookings_count || 0;
                    const capacity = trip.bus?.type?.capacity || 40;
                    return acc + (booked / capacity);
                }, 0);
                const avgOccupancy = Math.round((totalOccupancy / trips.length) * 100);
                setOccupancyRate(avgOccupancy);
            }

        } catch (err) {
            console.error("Failed to fetch widget data", err);
        }
    };

    // Lấy hoạt động gần đây (bookings)
    const fetchRecentActivities = async () => {
        try {
            setActivitiesLoading(true);
            const response = await adminBookingService.getBookings({ per_page: 5 });

            if (response.data?.data) {
                const bookings = response.data.data;
                const activities = bookings.map((booking) => {
                    let status = "success";
                    let message = "";
                    let icon = null;

                    const route = booking.legs?.[0]?.trip?.route;
                    const routeName = route
                        ? `${route.from_city?.name || ""} → ${route.to_city?.name || ""}`
                        : "N/A";

                    if (booking.status === "cancelled") {
                        status = "error";
                        message = `${booking.passenger_name || "Khách"} đã huỷ vé ${routeName}`;
                    } else if (booking.status === "paid") {
                        status = "success";
                        message = `${booking.passenger_name || "Khách"} đặt vé ${routeName}`;
                    } else if (booking.status === "pending") {
                        status = "warning";
                        message = `${booking.passenger_name || "Khách"} đang chờ thanh toán ${routeName}`;
                    } else {
                        status = "info";
                        message = `Đơn #${booking.code} - ${booking.status}`;
                    }

                    return {
                        id: booking.id,
                        type: booking.status,
                        message,
                        time: formatRelativeTime(booking.created_at),
                        status,
                        amount: booking.total_price,
                        code: booking.code
                    };
                });
                setRecentActivities(activities);
            }
        } catch (err) {
            console.error("Failed to fetch recent activities:", err);
        } finally {
            setActivitiesLoading(false);
        }
    };

    useEffect(() => {
        fetchDashboardData();
        fetchWidgetsData();
        fetchRecentActivities();
    }, []);

    const handleRefresh = () => {
        fetchDashboardData();
        fetchWidgetsData();
        fetchRecentActivities();
    };

    return (
        <div className="admin-dashboard">
            <div className="admin-dashboard__header">
                <div>
                    <h1 className="admin-dashboard__title">Tổng quan</h1>
                    <p className="admin-dashboard__subtitle">
                        Chào mừng trở lại! Đây là tổng quan về hệ thống của bạn.
                    </p>
                </div>
                <div className="admin-dashboard__actions">
                    <button
                        className="admin-dashboard__btn admin-dashboard__btn--primary"
                        onClick={handleRefresh}
                        disabled={loading}
                    >
                        {loading ? (
                            <Loader2 size={18} className="spin" />
                        ) : (
                            <RefreshCw size={18} />
                        )}
                        <span>Làm mới</span>
                    </button>
                </div>
            </div>

            {error && (
                <div className="admin-dashboard__error">
                    <span>{error}</span>
                    <button onClick={handleRefresh}>Thử lại</button>
                </div>
            )}

            <DashboardStats
                dashboardData={dashboardData}
                trendData={trendData}
                loading={loading}
            />

            <div className="admin-dashboard__grid">
                <LiveOperations trips={upcomingTrips} loading={loading} />
            </div>

            <div className="admin-dashboard__grid admin-dashboard__grid--bottom">
                <OccupancyRate occupancyData={occupancyRate} />

                <RecentActivities
                    recentActivities={recentActivities}
                    loading={activitiesLoading}
                />

                <QuickLinks />
            </div>

            <DashboardFeatures />
        </div>
    );
}

export default AdminDashboard;
