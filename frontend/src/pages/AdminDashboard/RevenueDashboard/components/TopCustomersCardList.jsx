import { User, Award, PhoneCall, Mail } from "lucide-react";
import "./TopCustomersCardList.scss";

const TopCustomersCardList = ({ data = [] }) => {
    if (!data || data.length === 0) {
        return (
            <div className="top-customers__empty">
                <p>Chưa có dữ liệu khách hàng</p>
            </div>
        );
    }

    // Get initials from name
    const getInitials = (name) => {
        if (!name) return "?";
        const parts = name.trim().split(" ");
        if (parts.length >= 2) {
            return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        }
        return name.substring(0, 2).toUpperCase();
    };

    // Get ranking emoji
    const getRankingBadge = (index) => {
        if (index === 0) return "🥇";
        if (index === 1) return "🥈";
        if (index === 2) return "🥉";
        return `#${index + 1}`;
    };

    // Check if customer is VIP (>= 1 million VND spent)
    const isVIP = (totalSpent) => totalSpent >= 1000000;

    // Format currency
    const formatCurrency = (amount) => {
        return new Intl.NumberFormat("vi-VN", {
            style: "currency",
            currency: "VND",
        }).format(amount);
    };

    // Mask phone number for privacy
    const maskPhone = (phone) => {
        if (!phone) return "N/A";
        const cleaned = phone.replace(/\D/g, "");
        if (cleaned.length >= 10) {
            return cleaned.substring(0, 3) + "****" + cleaned.substring(7);
        }
        return phone;
    };

    return (
        <div className="top-customers">
            <div className="top-customers__grid">
                {data.map((customer, index) => (
                    <div
                        key={customer.user_id}
                        className={`top-customers__card ${index < 3 ? "top-customers__card--podium" : ""
                            } ${isVIP(customer.total_spent) ? "top-customers__card--vip" : ""}`}
                    >
                        {/* Ranking Badge */}
                        <div className="top-customers__ranking">
                            {getRankingBadge(index)}
                        </div>

                        {/* VIP Badge */}
                        {isVIP(customer.total_spent) && (
                            <div className="top-customers__vip-badge">
                                <Award size={14} />
                                <span>VIP</span>
                            </div>
                        )}

                        {/* Avatar */}
                        <div className="top-customers__avatar">
                            <div className="top-customers__avatar-inner">
                                {getInitials(customer.name)}
                            </div>
                        </div>

                        {/* Customer Info */}
                        <div className="top-customers__info">
                            <h4 className="top-customers__name">
                                {customer.name || "Khách hàng"}
                            </h4>

                            <div className="top-customers__contact">
                                {customer.email && (
                                    <div className="top-customers__contact-item">
                                        <Mail size={14} />
                                        <span>{customer.email}</span>
                                    </div>
                                )}
                                {customer.phone && (
                                    <div className="top-customers__contact-item">
                                        <PhoneCall size={14} />
                                        <span>{maskPhone(customer.phone)}</span>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Stats */}
                        <div className="top-customers__stats">
                            <div className="top-customers__stat">
                                <span className="top-customers__stat-label">
                                    Tổng chi tiêu
                                </span>
                                <strong className="top-customers__stat-value top-customers__stat-value--primary">
                                    {formatCurrency(customer.total_spent)}
                                </strong>
                            </div>
                            <div className="top-customers__stat">
                                <span className="top-customers__stat-label">
                                    Số vé đã đặt
                                </span>
                                <strong className="top-customers__stat-value">
                                    {customer.booking_count} vé
                                </strong>
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
};

export default TopCustomersCardList;
