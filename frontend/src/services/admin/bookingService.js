import axiosClient from "../../apis/axiosClient";

export const adminBookingService = {
    async getBookings(params = {}) {
        const response = await axiosClient.get("/admin/bookings", { params });
        return response.data;
    },

    async createBooking(payload) {
        const response = await axiosClient.post("/admin/bookings", payload);
        return response.data;
    },

    async findUserByPhone(phone) {
        const response = await axiosClient.get("/users", {
            params: {
                keyword: phone,
                per_page: 1,
            },
        });
        return response.data;
    },

    async markAsPaid(bookingId) {
        const response = await axiosClient.post(
            `/admin/bookings/${bookingId}/mark-paid`,
        );
        return response.data;
    },

    async changeSeat(bookingId, bookingItemId, newSeatId) {
        const response = await axiosClient.post(
            `/admin/bookings/${bookingId}/change-seat`,
            {
                booking_item_id: bookingItemId,
                new_seat_id: newSeatId,
            },
        );
        return response.data;
    },

    async changeTrip(bookingId, bookingItemId, newTripId, options = {}) {
        const response = await axiosClient.post(
            `/admin/bookings/${bookingId}/change-trip`,
            {
                booking_item_id: bookingItemId,
                new_trip_id: newTripId,
                new_seat_id: options.newSeatId || null,
                pickup_location_id: options.pickupLocationId || null,
                dropoff_location_id: options.dropoffLocationId || null,
                pickup_address: options.pickupAddress || null,
                dropoff_address: options.dropoffAddress || null,
            },
        );
        return response.data;
    },

    async lookupByCode(code) {
        const response = await axiosClient.get("/admin/bookings/lookup", {
            params: {
                code,
            },
        });
        return response.data;
    },

    async getRefundPolicy(bookingId) {
        const response = await axiosClient.get(
            `/admin/bookings/${bookingId}/refund-policy`,
        );
        return response.data;
    },

    async refundPriceDifference(bookingId, payload) {
        const response = await axiosClient.post(
            `/admin/bookings/${bookingId}/refund-price-difference`,
            payload,
        );
        return response.data;
    },

    async refund(bookingId, payload) {
        const response = await axiosClient.post(
            `/admin/bookings/${bookingId}/refund`,
            payload,
        );
        return response.data;
    },

    async markAdditionalPaymentPaid(bookingId) {
        const response = await axiosClient.post(
            `/admin/bookings/${bookingId}/mark-additional-payment-paid`,
        );
        return response.data;
    },
};
