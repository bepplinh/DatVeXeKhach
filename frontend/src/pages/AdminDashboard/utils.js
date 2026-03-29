export const formatCurrency = (value) => {
    if (value === undefined || value === null) return "0đ";
    if (value >= 1e9) {
        return `${(value / 1e9).toFixed(1)} tỷ`;
    }
    if (value >= 1e6) {
        return `${(value / 1e6).toFixed(1)} triệu`;
    }
    if (value >= 1e3) {
        return `${(value / 1e3).toFixed(0)}k`;
    }
    return `${value.toLocaleString("vi-VN")}đ`;
};

export const formatChange = (change) => {
    if (change === undefined || change === null) return null;
    const sign = change >= 0 ? "+" : "";
    return `${sign}${change.toFixed(1)}%`;
};

export const formatRelativeTime = (dateString) => {
    if (!dateString) return "";
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return "Vừa xong";
    if (diffMins < 60) return `${diffMins} phút trước`;
    if (diffHours < 24) return `${diffHours} giờ trước`;
    return `${diffDays} ngày trước`;
};
