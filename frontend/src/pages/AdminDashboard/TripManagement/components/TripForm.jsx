import React, { useEffect, useState } from "react";
import {
    Dialog,
    DialogTitle,
    DialogContent,
    DialogActions,
    Button,
    FormControl,
    InputLabel,
    Select,
    MenuItem,
    CircularProgress,
    TextField,
} from "@mui/material";
import { DateTimePicker } from "@mui/x-date-pickers/DateTimePicker";
import { LocalizationProvider } from "@mui/x-date-pickers/LocalizationProvider";
import { AdapterDayjs } from "@mui/x-date-pickers/AdapterDayjs";
import dayjs from "dayjs";
import "dayjs/locale/vi";
import { X } from "lucide-react";
import { adminRouteService } from "../../../../services/admin/routeService";
import { adminBusService } from "../../../../services/admin/busService";
import { getErrorMessage } from "../../../../utils/error";
import "./TripForm.scss";

dayjs.locale("vi");

const defaultForm = {
    route_id: "",
    bus_id: "",
    departure_time: "",
    status: "scheduled",
};

const TripForm = ({ open, onClose, onSubmit, initialData = null }) => {
    const [formData, setFormData] = useState(defaultForm);
    const [errors, setErrors] = useState({});
    const [loading, setLoading] = useState(false);
    const [routes, setRoutes] = useState([]);
    const [buses, setBuses] = useState([]);
    const [loadingRoutes, setLoadingRoutes] = useState(false);
    const [loadingBuses, setLoadingBuses] = useState(false);

    useEffect(() => {
        const loadData = async () => {
            setLoadingRoutes(true);
            setLoadingBuses(true);

            try {
                const [routesRes, busesRes] = await Promise.all([
                    adminRouteService.getRoutes({ per_page: 1000 }),
                    adminBusService.getBuses({ per_page: 1000 }),
                ]);

                // Handle routes - check multiple possible response formats
                if (routesRes) {
                    let routesData = [];
                    if (Array.isArray(routesRes)) {
                        routesData = routesRes;
                    } else if (Array.isArray(routesRes.data)) {
                        routesData = routesRes.data;
                    } else if (routesRes.data?.data && Array.isArray(routesRes.data.data)) {
                        routesData = routesRes.data.data;
                    } else if (routesRes.routes && Array.isArray(routesRes.routes)) {
                        routesData = routesRes.routes;
                    }
                    setRoutes(routesData);
                }

                // Handle buses - check multiple possible response formats
                if (busesRes) {
                    let busesData = [];
                    if (Array.isArray(busesRes)) {
                        busesData = busesRes;
                    } else if (Array.isArray(busesRes.data)) {
                        busesData = busesRes.data;
                    } else if (busesRes.data?.data && Array.isArray(busesRes.data.data)) {
                        busesData = busesRes.data.data;
                    } else if (busesRes.buses && Array.isArray(busesRes.buses)) {
                        busesData = busesRes.buses;
                    }
                    setBuses(busesData);
                }
            } catch (error) {
                console.error("Failed to load form data", error);
                // Set empty arrays on error to prevent map errors
                setRoutes([]);
                setBuses([]);
            } finally {
                setLoadingRoutes(false);
                setLoadingBuses(false);
            }
        };

        if (open) {
            loadData();
        }
    }, [open]);

    useEffect(() => {
        if (initialData) {
            setFormData({
                route_id: initialData.route_id || "",
                bus_id: initialData.bus_id || "",
                departure_time: initialData.departure_time
                    ? dayjs(initialData.departure_time).format(
                        "YYYY-MM-DD HH:mm:ss"
                    )
                    : "",
                status: initialData.status || "scheduled",
            });
        } else {
            setFormData(defaultForm);
        }
        setErrors({});
    }, [initialData, open]);

    const handleChange = (field, value) => {
        setFormData((prev) => ({
            ...prev,
            [field]: value,
        }));
        if (errors[field]) {
            setErrors((prev) => ({ ...prev, [field]: "" }));
        }
    };

    const validate = () => {
        const newErrors = {};

        if (!formData.route_id) {
            newErrors.route_id = "Vui lòng chọn tuyến đường";
        }

        if (!formData.departure_time) {
            newErrors.departure_time = "Vui lòng chọn thời gian khởi hành";
        } else {
            const departureDate = dayjs(formData.departure_time);
            const now = dayjs();
            if (departureDate.isBefore(now) && !initialData) {
                newErrors.departure_time =
                    "Thời gian khởi hành phải ở tương lai";
            }
        }

        if (formData.status && !["scheduled", "running", "finished", "cancelled"].includes(formData.status)) {
            newErrors.status = "Trạng thái không hợp lệ";
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleSubmit = async () => {
        if (!validate()) return;

        setLoading(true);
        try {
            const submitData = {
                route_id: parseInt(formData.route_id),
                bus_id: formData.bus_id ? parseInt(formData.bus_id) : null,
                departure_time: dayjs(formData.departure_time).format(
                    "YYYY-MM-DD HH:mm:ss"
                ),
                status: formData.status || "scheduled",
            };

            await onSubmit(submitData);
            onClose();
        } catch (error) {
            setErrors((prev) => ({
                ...prev,
                submit: getErrorMessage(
                    error,
                    "Có lỗi xảy ra khi lưu chuyến xe. Vui lòng thử lại."
                ),
            }));
        } finally {
            setLoading(false);
        }
    };

    const handleClose = () => {
        if (loading) return;
        setFormData(defaultForm);
        setErrors({});
        onClose();
    };

    const statusOptions = [
        { value: "scheduled", label: "Đã lên lịch" },
        { value: "running", label: "Đang chạy" },
        { value: "finished", label: "Hoàn thành" },
        { value: "cancelled", label: "Đã hủy" },
    ];

    return (
        <Dialog
            open={open}
            onClose={handleClose}
            fullWidth
            maxWidth="sm"
            className="trip-form-dialog"
            transitionDuration={{ enter: 0, exit: 0 }}
        >
            <DialogTitle className="trip-form__title">
                {initialData ? "Chỉnh sửa chuyến xe" : "Thêm chuyến xe mới"}
                <button
                    className="trip-form__close-btn"
                    onClick={handleClose}
                    disabled={loading}
                >
                    <X size={18} />
                </button>
            </DialogTitle>
            <DialogContent className="trip-form__content">
                <div className="trip-form__fields">
                    <FormControl
                        fullWidth
                        className="trip-form__field"
                        error={!!errors.route_id}
                    >
                        <InputLabel id="route-label">
                            Tuyến đường *
                        </InputLabel>
                        <Select
                            labelId="route-label"
                            value={formData.route_id || ""}
                            label="Tuyến đường *"
                            onChange={(e) =>
                                handleChange("route_id", e.target.value)
                            }
                            disabled={loading || loadingRoutes}
                            endAdornment={
                                loadingRoutes ? (
                                    <CircularProgress size={18} />
                                ) : null
                            }
                        >
                            {Array.isArray(routes) && routes.map((route) => (
                                <MenuItem key={route.id} value={route.id}>
                                    {route.name || `Tuyến #${route.id}`}
                                </MenuItem>
                            ))}
                        </Select>
                        {errors.route_id && (
                            <span className="trip-form__error">
                                {errors.route_id}
                            </span>
                        )}
                    </FormControl>

                    <FormControl
                        fullWidth
                        className="trip-form__field"
                        error={!!errors.bus_id}
                    >
                        <InputLabel id="bus-label">Xe bus</InputLabel>
                        <Select
                            labelId="bus-label"
                            value={formData.bus_id || ""}
                            label="Xe bus"
                            onChange={(e) =>
                                handleChange("bus_id", e.target.value)
                            }
                            disabled={loading || loadingBuses}
                            endAdornment={
                                loadingBuses ? (
                                    <CircularProgress size={18} />
                                ) : null
                            }
                        >
                            <MenuItem value="">Không chọn</MenuItem>
                            {Array.isArray(buses) && buses.map((bus) => (
                                <MenuItem key={bus.id} value={bus.id}>
                                    {bus.license_plate || bus.plate_number || `Xe #${bus.id}`}
                                </MenuItem>
                            ))}
                        </Select>
                        {errors.bus_id && (
                            <span className="trip-form__error">
                                {errors.bus_id}
                            </span>
                        )}
                    </FormControl>

                    <LocalizationProvider
                        dateAdapter={AdapterDayjs}
                        adapterLocale="vi"
                    >
                        <FormControl
                            fullWidth
                            className="trip-form__field"
                            error={!!errors.departure_time}
                        >
                            <DateTimePicker
                                label="Thời gian khởi hành *"
                                value={
                                    formData.departure_time
                                        ? dayjs(formData.departure_time)
                                        : null
                                }
                                onChange={(date) =>
                                    handleChange(
                                        "departure_time",
                                        date
                                            ? date.format("YYYY-MM-DD HH:mm:ss")
                                            : ""
                                    )
                                }
                                disabled={loading}
                                minDateTime={!initialData ? dayjs() : null}
                                slotProps={{
                                    textField: {
                                        fullWidth: true,
                                        error: !!errors.departure_time,
                                        helperText: errors.departure_time,
                                    },
                                }}
                            />
                        </FormControl>
                    </LocalizationProvider>

                    <FormControl
                        fullWidth
                        className="trip-form__field"
                        error={!!errors.status}
                    >
                        <InputLabel id="status-label">Trạng thái</InputLabel>
                        <Select
                            labelId="status-label"
                            value={formData.status || "scheduled"}
                            label="Trạng thái"
                            onChange={(e) =>
                                handleChange("status", e.target.value)
                            }
                            disabled={loading}
                        >
                            {statusOptions.map((option) => (
                                <MenuItem key={option.value} value={option.value}>
                                    {option.label}
                                </MenuItem>
                            ))}
                        </Select>
                        {errors.status && (
                            <span className="trip-form__error">
                                {errors.status}
                            </span>
                        )}
                    </FormControl>

                    {errors.submit && (
                        <div className="trip-form__submit-error">
                            {errors.submit}
                        </div>
                    )}
                </div>
            </DialogContent>
            <DialogActions className="trip-form__actions">
                <Button
                    onClick={handleClose}
                    className="trip-form__cancel-btn"
                    disabled={loading}
                >
                    Hủy
                </Button>
                <Button
                    onClick={handleSubmit}
                    className="trip-form__submit-btn"
                    disabled={loading}
                    variant="contained"
                >
                    {loading
                        ? "Đang lưu..."
                        : initialData
                            ? "Cập nhật"
                            : "Thêm"}
                </Button>
            </DialogActions>
        </Dialog>
    );
};

export default TripForm;

