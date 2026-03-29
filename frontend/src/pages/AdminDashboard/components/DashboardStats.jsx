import React from "react";
import {
    DollarSign,
    Ticket,
    CreditCard,
    Calendar,
    TrendingUp,
    TrendingDown
} from "lucide-react";
import { formatCurrency, formatChange } from "../utils";

const DashboardStats = ({ dashboardData, trendData, loading }) => {
    const getStatCards = () => {
        if (!dashboardData?.data) return [];

        const { current_period, previous_period, comparison } = dashboardData.data;

        // Ensure trendData is an array and has data
        const data = Array.isArray(trendData) ? trendData : [];

        return [
            {
                id: "revenue",
                title: "Doanh thu hôm nay",
                value: formatCurrency(current_period?.revenue),
                change: formatChange(comparison?.revenue_change),
                trend: comparison?.revenue_change >= 0 ? "up" : "down",
                icon: DollarSign,
                color: "orange",
                sparklineData: data.map(item => ({ value: item.revenue })),
                sparklineColor: "#f97316" // orange-500
            },
            {
                id: "bookings",
                title: "Đơn đặt vé hôm nay",
                value: current_period?.booking_count?.toLocaleString("vi-VN") || "0",
                change: formatChange(comparison?.booking_change),
                trend: comparison?.booking_change >= 0 ? "up" : "down",
                icon: Ticket,
                color: "green",
                sparklineData: data.map(item => ({ value: item.booking_count })),
                sparklineColor: "#10b981" // emerald-500
            },
            {
                id: "prev_revenue",
                title: "Doanh thu hôm qua",
                value: formatCurrency(previous_period?.revenue),
                change: null,
                trend: "up",
                icon: CreditCard,
                color: "blue",
                sparklineData: data.map(item => ({ value: item.revenue })).reverse(), // Just for visual variation if real data is missing specific prev period
                sparklineColor: "#3b82f6" // blue-500
            },
            {
                id: "prev_bookings",
                title: "Đơn đặt vé hôm qua",
                value: previous_period?.booking_count?.toLocaleString("vi-VN") || "0",
                change: null,
                trend: "up",
                icon: Calendar,
                color: "purple",
                sparklineData: data.map(item => ({ value: item.booking_count })).reverse(),
                sparklineColor: "#8b5cf6" // violet-500
            },
        ];
    };

    const statCards = getStatCards();

    return (
        <div className="admin-dashboard__stats">
            {loading ? (
                Array.from({ length: 4 }).map((_, index) => (
                    <div key={index} className="admin-stat-card admin-stat-card--loading">
                        <div className="admin-stat-card__content">Loading...</div>
                    </div>
                ))
            ) : (
                statCards.map((stat) => {
                    const Icon = stat.icon;
                    const TrendIcon = stat.trend === "up" ? TrendingUp : TrendingDown;
                    return (
                        <div
                            key={stat.id}
                            className={`admin-stat-card admin-stat-card--${stat.color}`}
                        >
                            <div className="admin-stat-card__icon">
                                <Icon size={24} />
                            </div>
                            <div className="admin-stat-card__content">
                                <div className="admin-stat-card__label">
                                    {stat.title}
                                </div>
                                <div className="admin-stat-card__value">
                                    {stat.value}
                                </div>
                                {stat.change && (
                                    <div
                                        className={`admin-stat-card__change admin-stat-card__change--${stat.trend}`}
                                    >
                                        <TrendIcon size={14} />
                                        <span>{stat.change}</span>
                                        <span className="admin-stat-card__change-label">
                                            so với hôm qua
                                        </span>
                                    </div>
                                )}
                            </div>
                        </div>
                    );
                })
            )}
        </div>
    );
};

export default DashboardStats;
