import React from "react";
import { ArrowRight, CheckCircle, XCircle, Clock, Ticket, Info } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { formatCurrency } from "../utils";

const RecentActivities = ({ recentActivities, loading }) => {
    const navigate = useNavigate();

    const getIcon = (status) => {
        switch (status) {
            case "success": return CheckCircle;
            case "error": return XCircle;
            case "warning": return Clock;
            case "info": return Ticket;
            default: return Info;
        }
    };

    return (
        <div className="admin-dashboard__card h-full">
            <div className="admin-dashboard__card-header">
                <h2 className="admin-dashboard__card-title">
                    Hoạt động gần đây
                </h2>
                <button
                    className="admin-dashboard__card-action"
                    onClick={() => navigate("/admin/bookings")}
                >
                    Xem tất cả
                </button>
            </div>
            <div className="admin-dashboard__card-body">
                {loading ? (
                    <div className="admin-activity-list">
                        <p className="text-gray-500 text-center py-4">Đang tải...</p>
                    </div>
                ) : recentActivities && recentActivities.length > 0 ? (
                    <div className="admin-activity-list">
                        {recentActivities.map((activity) => {
                            const Icon = getIcon(activity.status);
                            // Note: We need to pass the component reference or handle it differently if Icon is a function/component
                            // In AdminDashboard.jsx it was passing the component itself.
                            // If `activity.icon` is already the component (e.g. CheckCircle), we can use it directly.
                            // However, we need to ensure the parent is passing it correctly.

                            return (
                                <div
                                    key={activity.id}
                                    className={`admin-activity-item admin-activity-item--${activity.status}`}
                                    onClick={() => navigate(`/admin/bookings`)}
                                >
                                    <div className="admin-activity-item__icon">
                                        <Icon size={18} />
                                    </div>
                                    <div className="admin-activity-item__content">
                                        <div className="admin-activity-item__message truncate max-w-[200px] md:max-w-[300px]">
                                            {activity.message}
                                        </div>
                                        <div className="admin-activity-item__meta">
                                            <span className="admin-activity-item__time">
                                                {activity.time}
                                            </span>
                                            {activity.amount > 0 && (
                                                <span className="admin-activity-item__amount">
                                                    {formatCurrency(activity.amount)}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    <ArrowRight size={16} className="admin-activity-item__arrow" />
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <div className="admin-dashboard__empty">
                        Chưa có hoạt động nào
                    </div>
                )}
            </div>
        </div>
    );
};

export default RecentActivities;
