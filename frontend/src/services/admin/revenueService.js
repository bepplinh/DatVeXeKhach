import apiClient from "../../apis/axiosClient";

export const revenueService = {
    /**
     * Lấy dữ liệu dashboard doanh thu
     * @param {Object} params - { period: 'day'|'week'|'month'|'quarter'|'year', date: 'YYYY-MM-DD' }
     */
    async getDashboard(params = {}) {
        const { period = "day", date } = params;
        const queryParams = new URLSearchParams({
            period,
        });
        if (date) queryParams.append("date", date);

        const res = await apiClient.get(
            `/admin/revenue/dashboard?${queryParams.toString()}`,
        );
        return res.data;
    },

    /**
     * Lấy dữ liệu xu hướng doanh thu
     * @param {Object} params - { period: 'day'|'week'|'month'|'quarter'|'year', from_date: 'YYYY-MM-DD', to_date: 'YYYY-MM-DD' }
     */
    async getTrend(params = {}) {
        const { period = "day", from_date, to_date } = params;
        const queryParams = new URLSearchParams({
            period,
        });
        if (from_date) queryParams.append("from_date", from_date);
        if (to_date) queryParams.append("to_date", to_date);

        const res = await apiClient.get(
            `/admin/revenue/trend?${queryParams.toString()}`,
        );
        return res.data;
    },

    /**
     * Lấy top tuyến đường
     * @param {Object} params - { limit: number, from_date: 'YYYY-MM-DD', to_date: 'YYYY-MM-DD' }
     */
    async getTopRoutes(params = {}) {
        const { limit = 10, from_date, to_date } = params;
        const queryParams = new URLSearchParams({
            limit: limit.toString(),
        });
        if (from_date) queryParams.append("from_date", from_date);
        if (to_date) queryParams.append("to_date", to_date);

        const res = await apiClient.get(
            `/admin/revenue/top-routes?${queryParams.toString()}`,
        );
        return res.data;
    },

    /**
     * Lấy top chuyến xe
     * @param {Object} params - { limit: number, from_date: 'YYYY-MM-DD', to_date: 'YYYY-MM-DD' }
     */
    async getTopTrips(params = {}) {
        const { limit = 10, from_date, to_date } = params;
        const queryParams = new URLSearchParams({
            limit: limit.toString(),
        });
        if (from_date) queryParams.append("from_date", from_date);
        if (to_date) queryParams.append("to_date", to_date);

        const res = await apiClient.get(
            `/admin/revenue/top-trips?${queryParams.toString()}`,
        );
        return res.data;
    },

    /**
     * Lấy top khách hàng
     * @param {Object} params - { limit: number, from_date: 'YYYY-MM-DD', to_date: 'YYYY-MM-DD' }
     */
    async getTopCustomers(params = {}) {
        const { limit = 10, from_date, to_date } = params;
        const queryParams = new URLSearchParams({
            limit: limit.toString(),
        });
        if (from_date) queryParams.append("from_date", from_date);
        if (to_date) queryParams.append("to_date", to_date);

        const res = await apiClient.get(
            `/admin/revenue/top-customers?${queryParams.toString()}`,
        );
        return res.data;
    },

    /**
     * Phân tích doanh thu chi tiết
     * @param {Object} params - { group_by: 'route'|'bus_type'|'payment_method'|'source'|'hour', from_date: 'YYYY-MM-DD', to_date: 'YYYY-MM-DD' }
     */
    async getAnalysis(params = {}) {
        const { group_by = "route", from_date, to_date } = params;
        const queryParams = new URLSearchParams({
            group_by,
        });
        if (from_date) queryParams.append("from_date", from_date);
        if (to_date) queryParams.append("to_date", to_date);

        const res = await apiClient.get(
            `/admin/revenue/analysis?${queryParams.toString()}`,
        );
        return res.data;
    },
};
