import React from "react";
import { DataGrid } from "@mui/x-data-grid";
import { Box } from "@mui/material";

const ModificationHistoryDataGrid = ({ transactions, loading }) => {
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
            field: "type_label",
            headerName: "Loại giao dịch",
            width: 180,
            renderCell: (params) => {
                const isIncrease = params.row.type === "price_increase";
                return (
                    <span
                        className={`payment-status ${isIncrease ? "status-warning" : "status-info"}`}
                    >
                        {params.value}
                    </span>
                );
            }
        },
        {
            field: "amount",
            headerName: "Số tiền",
            width: 140,
            renderCell: (params) => {
                const isIncrease = params.row.type === "price_increase";
                const color = isIncrease ? "#28a745" : "#dc3545"; // Green for payment in, Red for refund out
                const prefix = isIncrease ? "+" : "-";
                return (
                    <span style={{ fontWeight: 600, color }}>
                        {prefix}{formatCurrency(params.value)}
                    </span>
                );
            },
        },
        {
            field: "reason",
            headerName: "Lý do",
            width: 220,
            flex: 1,
        },
        {
            field: "status",
            headerName: "Trạng thái",
            width: 130,
            renderCell: (params) => {
                const statusMap = {
                    succeeded: { label: "Thành công", class: "status-success" },
                    pending: { label: "Chờ xử lý", class: "status-warning" },
                    refunded: { label: "Đã hoàn", class: "status-success" },
                    failed: { label: "Thất bại", class: "status-danger" },
                };
                const conf = statusMap[params.value] || { label: params.value, class: "status-default" };
                return <span className={`payment-status ${conf.class}`}>{conf.label}</span>;
            }
        },
        {
            field: "created_at",
            headerName: "Thời gian",
            width: 160,
            renderCell: (params) => formatDate(params.value),
        },
    ];

    const rows = (transactions || []).map((t) => ({
        id: t.id,
        ...t,
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

export default ModificationHistoryDataGrid;
