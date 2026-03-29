import React from "react";
import { DataGrid } from "@mui/x-data-grid";
import { Box } from "@mui/material";

const RefundHistoryDataGrid = ({ refunds, loading }) => {
    const formatCurrency = (amount) => {
        return new Intl.NumberFormat("vi-VN", {
            style: "currency",
            currency: "VND",
        }).format(amount || 0);
    };

    const formatDate = (dateString) => {
        if (!dateString) return "-";
        const date = new Date(dateString);
        return date.toLocaleString("vi-VN", {
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
        });
    };

    const columns = [
        {
            field: "booking_code",
            headerName: "Mã đặt vé",
            width: 130,
            renderCell: (params) => (
                <span style={{ fontWeight: 600, color: "#0d6efd" }}>
                    {params.value}
                </span>
            ),
        },
        {
            field: "customer",
            headerName: "Khách hàng",
            flex: 1,
            minWidth: 150,
        },
        {
            field: "amount",
            headerName: "Số tiền hoàn",
            width: 140,
            renderCell: (params) => (
                <span style={{ fontWeight: 600, color: "#dc3545" }}>
                    {formatCurrency(params.value)}
                </span>
            ),
        },
        {
            field: "reason",
            headerName: "Lý do",
            width: 200,
            flex: 1,
        },
        {
            field: "refunded_at",
            headerName: "Thời gian hoàn",
            width: 160,
            renderCell: (params) => formatDate(params.value),
        },
        {
            field: "type_label",
            headerName: "Loại hoàn tiền",
            width: 160,
            renderCell: (params) => (
                <span className="payment-status status-default">
                    {params.value}
                </span>
            )
        },
    ];

    const rows = (refunds || []).map((refund) => ({
        id: refund.id,
        ...refund,
    }));

    return (
        <Box sx={{ height: 600, width: "100%" }}>
            <DataGrid
                rows={rows}
                columns={columns}
                pageSize={20}
                rowsPerPageOptions={[20, 50, 100]}
                disableSelectionOnClick
                loading={loading}
                autoHeight
                sx={{
                    border: "none",
                    "& .MuiDataGrid-cell:focus": { outline: "none" },
                    "& .MuiDataGrid-row:hover": { cursor: "pointer", backgroundColor: "rgba(0,0,0,0.02)" },
                }}
            />
        </Box>
    );
};

export default RefundHistoryDataGrid;
