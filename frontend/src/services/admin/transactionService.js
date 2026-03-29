import apiClient from "../../apis/axiosClient";

export const transactionService = {
    // Lấy lịch sử hoàn tiền
    async getRefunds(params = {}) {
        const queryParams = new URLSearchParams();
        Object.keys(params).forEach((key) => {
            if (params[key] !== null && params[key] !== "") {
                queryParams.append(key, params[key]);
            }
        });

        const response = await apiClient.get(
            `/admin/transactions/refunds?${queryParams.toString()}`,
        );
        return response.data;
    },

    // Lấy lịch sử thay đổi (đổi vé/ghế có phát sinh tài chính)
    async getModifications(params = {}) {
        const queryParams = new URLSearchParams();
        Object.keys(params).forEach((key) => {
            if (params[key] !== null && params[key] !== "") {
                queryParams.append(key, params[key]);
            }
        });

        const response = await apiClient.get(
            `/admin/transactions/modifications?${queryParams.toString()}`,
        );
        return response.data;
    },
};
