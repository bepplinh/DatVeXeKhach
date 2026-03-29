import React from "react";
import { useNavigate } from "react-router-dom";
import { DollarSign, Ticket, Calendar, MessageCircle } from "lucide-react";

const DashboardFeatures = () => {
    const navigate = useNavigate();

    return (
        <div className="admin-dashboard__card admin-dashboard__card--full">
            <div className="admin-dashboard__card-header">
                <h2 className="admin-dashboard__card-title">
                    Các tính năng quản lý
                </h2>
            </div>
            <div className="admin-dashboard__card-body">
                <div className="admin-feature-grid">
                    <div
                        className="admin-feature-item"
                        onClick={() => navigate("/admin/revenue")}
                    >
                        <DollarSign />
                        <div className="admin-feature-item__content">
                            <h3>Báo cáo doanh thu</h3>
                            <p>Xem biểu đồ, xu hướng và phân tích chi tiết</p>
                        </div>
                    </div>
                    <div
                        className="admin-feature-item"
                        onClick={() => navigate("/admin/bookings")}
                    >
                        <Ticket />
                        <div className="admin-feature-item__content">
                            <h3>Quản lý đặt vé</h3>
                            <p>Tạo vé, thay đổi chỗ ngồi, hoàn tiền</p>
                        </div>
                    </div>
                    <div
                        className="admin-feature-item"
                        onClick={() => navigate("/admin/trips")}
                    >
                        <Calendar />
                        <div className="admin-feature-item__content">
                            <h3>Quản lý chuyến xe</h3>
                            <p>Tạo và quản lý các chuyến xe</p>
                        </div>
                    </div>
                    <div
                        className="admin-feature-item"
                        onClick={() => navigate("/admin/chat")}
                    >
                        <MessageCircle />
                        <div className="admin-feature-item__content">
                            <h3>Hỗ trợ khách hàng</h3>
                            <p>Chat trực tiếp với khách hàng</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default DashboardFeatures;
