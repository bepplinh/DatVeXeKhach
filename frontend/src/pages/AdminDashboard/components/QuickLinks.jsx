import React from "react";
import { useNavigate } from "react-router-dom";
import {
    Users,
    Ticket,
    Bus,
    DollarSign,
    Calendar,
    MessageCircle,
    Tag,
    Route as RouteIcon
} from "lucide-react";

const QUICK_LINKS = [
    { id: "bookings", label: "Đặt vé", icon: Ticket, path: "/admin/bookings" },
    { id: "trips", label: "Chuyến xe", icon: Calendar, path: "/admin/trips" },
    { id: "users", label: "Người dùng", icon: Users, path: "/admin/users" },
    { id: "revenue", label: "Doanh thu", icon: DollarSign, path: "/admin/revenue" },
    { id: "routes", label: "Tuyến đường", icon: RouteIcon, path: "/admin/routes" },
    { id: "buses", label: "Xe", icon: Bus, path: "/admin/buses" },
    { id: "coupons", label: "Khuyến mãi", icon: Tag, path: "/admin/coupons" },
    { id: "chat", label: "Hỗ trợ", icon: MessageCircle, path: "/admin/chat" },
];

const QuickLinks = () => {
    const navigate = useNavigate();

    return (
        <div className="admin-dashboard__card">
            <div className="admin-dashboard__card-header">
                <h2 className="admin-dashboard__card-title">
                    Truy cập nhanh
                </h2>
            </div>
            <div className="admin-dashboard__card-body">
                <div className="admin-quick-links">
                    {QUICK_LINKS.map((link) => {
                        const Icon = link.icon;
                        return (
                            <button
                                key={link.id}
                                className="admin-quick-link"
                                onClick={() => navigate(link.path)}
                            >
                                <Icon size={20} />
                                <span>{link.label}</span>
                            </button>
                        );
                    })}
                </div>
            </div>
        </div>
    );
};

export default QuickLinks;
