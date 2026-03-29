import { useEffect, useMemo, useState } from "react";
import {
    Sparkles,
    MapPin,
    Flag,
    Route,
    Compass,
    Target,
    ArrowRight,
    ShieldCheck,
} from "lucide-react";
import CircularIndeterminate from "../../../components/Loading/Loading";
import { routeOptimizationService } from "../../../services/admin/routeOptimizationService";
import { getErrorMessage } from "../../../utils/error";
import "./RouteOptimization.scss";

const optimizeModes = [
    {
        key: "dropoff",
        title: "Tối ưu trả khách",
        description: "Sắp xếp thứ tự trả khách tại điểm đến",
        accent: "primary",
    },
    {
        key: "pickup",
        title: "Tối ưu đón khách",
        description: "Sắp xếp thứ tự đón khách tại điểm xuất phát",
        accent: "success",
    },
];

const today = new Date().toISOString().split("T")[0];

const formatDateVi = (isoDate) => {
    if (!isoDate) return "";
    const [year, month, day] = isoDate.split("-");
    return `${day}/${month}/${year}`;
};

const RouteOptimizationPage = () => {
    const [selectedDate, setSelectedDate] = useState(today);
    const [tripList, setTripList] = useState([]);
    const [tripListLoading, setTripListLoading] = useState(false);
    const [tripListError, setTripListError] = useState("");

    const [selectedTripSummary, setSelectedTripSummary] = useState(null);
    const [tripData, setTripData] = useState(null);
    const [tripLoading, setTripLoading] = useState(false);
    const [tripError, setTripError] = useState("");

    const [optimizeType, setOptimizeType] = useState("dropoff");
    const [startPickupLocation, setStartPickupLocation] = useState("");
    const [startDropoffLocation, setStartDropoffLocation] = useState("");

    const [optimizationResult, setOptimizationResult] = useState(null);
    const [optimizeLoading, setOptimizeLoading] = useState(false);
    const [optimizeError, setOptimizeError] = useState("");

    const [lastOptimizedTrip, setLastOptimizedTrip] = useState(null);

    const relevantLocations = useMemo(() => {
        if (!tripData) return [];
        return optimizeType === "pickup"
            ? tripData.pickup_locations || []
            : tripData.dropoff_locations || [];
    }, [tripData, optimizeType]);

    useEffect(() => {
        if (!tripData) return;
        if (optimizeType === "pickup" && !startPickupLocation) {
            setStartPickupLocation(relevantLocations[0]?.address || "");
        }
        if (optimizeType === "dropoff" && !startDropoffLocation) {
            setStartDropoffLocation(relevantLocations[0]?.address || "");
        }
    }, [
        tripData,
        optimizeType,
        relevantLocations,
        startPickupLocation,
        startDropoffLocation,
    ]);

    const resetDetailState = () => {
        setTripData(null);
        setStartPickupLocation("");
        setStartDropoffLocation("");
        setOptimizationResult(null);
        setTripError("");
        setOptimizeError("");
        setLastOptimizedTrip(null);
    };

    useEffect(() => {
        const loadTrips = async () => {
            setTripListLoading(true);
            setTripListError("");
            setTripList([]);
            setSelectedTripSummary(null);
            resetDetailState();
            try {
                const response = await routeOptimizationService.listTrips({
                    date: selectedDate,
                });
                if (!response?.success) {
                    throw new Error("Không thể tải danh sách trips");
                }
                setTripList(response.data?.trips || []);
            } catch (error) {
                setTripListError(
                    getErrorMessage(
                        error,
                        "Không thể tải danh sách trips cho ngày này."
                    )
                );
            } finally {
                setTripListLoading(false);
            }
        };

        loadTrips();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectedDate]);

    const handleSelectTrip = async (trip) => {
        setSelectedTripSummary(trip);
        resetDetailState();
        setTripLoading(true);

        try {
            const response = await routeOptimizationService.fetchTripLocations(
                trip.id
            );
            if (!response?.success) {
                throw new Error("Không thể tải dữ liệu trip");
            }
            setTripData(response.data);
            setStartPickupLocation(
                response.data?.pickup_locations?.[0]?.address || ""
            );
            setStartDropoffLocation(
                response.data?.dropoff_locations?.[0]?.address || ""
            );
        } catch (error) {
            setTripData(null);
            setTripError(
                getErrorMessage(
                    error,
                    "Không thể tải thông tin địa điểm. Vui lòng thử lại."
                )
            );
        } finally {
            setTripLoading(false);
        }
    };

    const handleOptimize = async () => {
        if (!tripData?.trip_id) {
            setOptimizeError("Bạn cần chọn Trip trước khi tối ưu.");
            return;
        }
        if (relevantLocations.length === 0) {
            setOptimizeError(
                "Chưa có địa điểm phù hợp để tối ưu. Vui lòng kiểm tra lại."
            );
            return;
        }

        setOptimizeLoading(true);
        setOptimizeError("");

        try {
            const response = await routeOptimizationService.optimizeTrip({
                tripId: tripData.trip_id,
                optimizeType,
                startPickupLocation,
                startDropoffLocation,
            });

            if (!response?.success) {
                throw new Error("Không thể tối ưu tuyến");
            }

            setOptimizationResult(response.data);
            setLastOptimizedTrip({
                tripId: tripData.trip_id,
                optimizeType,
                startedFrom:
                    optimizeType === "pickup"
                        ? startPickupLocation
                        : startDropoffLocation,
            });
        } catch (error) {
            setOptimizationResult(null);
            setOptimizeError(
                getErrorMessage(
                    error,
                    "Không thể tối ưu tuyến ngay lúc này."
                )
            );
        } finally {
            setOptimizeLoading(false);
        }
    };

    const handleSelectStart = (address) => {
        if (optimizeType === "pickup") {
            setStartPickupLocation(address);
        } else {
            setStartDropoffLocation(address);
        }
    };

    const renderLocationList = (locations, type) => {
        if (!tripData) {
            return (
                <div className="route-opt__empty-state">
                    <p>Chưa có dữ liệu. Hãy nhập Trip ID để lấy địa điểm.</p>
                </div>
            );
        }

        if (!locations?.length) {
            return (
                <div className="route-opt__empty-state">
                    <p>Không có địa điểm {type === "pickup" ? "đón" : "trả"}.</p>
                </div>
            );
        }

        return (
            <div className="route-opt__location-list">
                {locations.map((location) => (
                    <div className="route-opt__location-card" key={location.address}>
                        <div className="route-opt__location-icon">
                            <MapPin size={16} />
                        </div>
                        <div className="route-opt__location-info">
                            <p className="route-opt__location-address">
                                {location.address}
                            </p>
                            <div className="route-opt__location-meta">
                                <span>
                                    {location.booking_leg_ids?.length || 0} booking
                                </span>
                                {location.booking_codes?.length > 0 && (
                                    <span className="route-opt__location-codes">
                                        {location.booking_codes.join(", ")}
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        );
    };

    return (
        <div className="route-opt">

            <div className="route-opt__panel route-opt__panel--list">
                <div className="route-opt__panel-header">
                    <div>
                        <h2>Chuyến xe trong ngày</h2>
                        <p>Chọn một trip để xem và tối ưu thứ tự đón/trả.</p>
                    </div>
                    <div className="route-opt__date-picker">
                        <label htmlFor="trip-date">Ngày</label>
                        <input
                            id="trip-date"
                            type="date"
                            value={selectedDate}
                            onChange={(e) => setSelectedDate(e.target.value)}
                        />
                    </div>
                </div>

                {tripListLoading ? (
                    <div className="route-opt__loader route-opt__loader--inline">
                        <CircularIndeterminate />
                    </div>
                ) : tripListError ? (
                    <div className="route-opt__error">{tripListError}</div>
                ) : tripList.length === 0 ? (
                    <div className="route-opt__empty-state">
                        <p>
                            Không có chuyến nào trong ngày{" "}
                            {formatDateVi(selectedDate)}.
                        </p>
                    </div>
                ) : (
                    <div className="route-opt__trip-grid">
                        {tripList.map((trip) => {
                            const isActive =
                                selectedTripSummary?.id === trip.id;
                            return (
                                <button
                                    key={trip.id}
                                    type="button"
                                    className={`route-opt__trip-card ${isActive
                                        ? "route-opt__trip-card--active"
                                        : ""
                                        }`}
                                    onClick={() => handleSelectTrip(trip)}
                                >
                                    <div className="route-opt__trip-card-header">
                                        <span>Trip #{trip.id}</span>
                                        <strong>{trip.departure_time}</strong>
                                    </div>
                                    <p className="route-opt__trip-route">
                                        {trip.route?.from_city || "—"} →{" "}
                                        {trip.route?.to_city || "—"}
                                    </p>
                                    <div className="route-opt__trip-meta">
                                        <span>
                                            <MapPin size={14} /> Đón:{" "}
                                            {trip.pickup_count}
                                        </span>
                                        <span>
                                            <MapPin size={14} /> Trả:{" "}
                                            {trip.dropoff_count}
                                        </span>
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                )}
            </div>

            {tripLoading && (
                <div className="route-opt__loader">
                    <CircularIndeterminate />
                </div>
            )}

            {tripData && !tripLoading && (
                <>
                    <div className="route-opt__grid">
                        <div className="route-opt__card route-opt__card--info">
                            <div className="route-opt__card-header">
                                <Flag size={18} />
                                <div>
                                    <p className="route-opt__card-label">
                                        Thông tin tuyến
                                    </p>
                                    <h3>Trip #{tripData.trip_id}</h3>
                                </div>
                            </div>
                            <div className="route-opt__route-brief">
                                <div>
                                    <span>Điểm xuất phát</span>
                                    <strong>{tripData.route?.from_city || "-"}</strong>
                                </div>
                                <ArrowRight size={18} />
                                <div>
                                    <span>Điểm đến</span>
                                    <strong>{tripData.route?.to_city || "-"}</strong>
                                </div>
                            </div>
                            <div className="route-opt__stats">
                                <div>
                                    <p>Điểm đón</p>
                                    <strong>
                                        {tripData.pickup_locations?.length || 0}
                                    </strong>
                                </div>
                                <div>
                                    <p>Điểm trả</p>
                                    <strong>
                                        {tripData.dropoff_locations?.length || 0}
                                    </strong>
                                </div>
                                <div>
                                    <p>Loại tối ưu</p>
                                    <strong>
                                        {optimizeType === "pickup"
                                            ? "Đón khách"
                                            : "Trả khách"}
                                    </strong>
                                </div>
                            </div>
                        </div>

                        <div className="route-opt__card route-opt__card--modes">
                            <div className="route-opt__card-header">
                                <Compass size={18} />
                                <div>
                                    <p className="route-opt__card-label">
                                        Chọn chế độ tối ưu
                                    </p>
                                    <h3>Focusing direction</h3>
                                </div>
                            </div>
                            <div className="route-opt__mode-grid">
                                {optimizeModes.map((mode) => (
                                    <button
                                        key={mode.key}
                                        type="button"
                                        className={`route-opt__mode-card route-opt__mode-card--${mode.accent} ${optimizeType === mode.key
                                            ? "route-opt__mode-card--active"
                                            : ""
                                            }`}
                                        onClick={() => {
                                            setOptimizeType(mode.key);
                                            setOptimizationResult(null);
                                            setOptimizeError("");
                                        }}
                                    >
                                        <div>
                                            <h4>{mode.title}</h4>
                                            <p>{mode.description}</p>
                                        </div>
                                        <Target size={18} />
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="route-opt__card route-opt__card--start">
                            <div className="route-opt__card-header">
                                <MapPin size={18} />
                                <div>
                                    <p className="route-opt__card-label">
                                        Điểm bắt đầu
                                    </p>
                                    <h3>
                                        {optimizeType === "pickup"
                                            ? "Chọn điểm đón đầu tiên"
                                            : "Chọn điểm trả đầu tiên"}
                                    </h3>
                                </div>
                            </div>
                            {relevantLocations.length === 0 ? (
                                <div className="route-opt__empty-state">
                                    <p>
                                        Không có địa điểm phù hợp. Vui lòng kiểm tra lại
                                        dữ liệu.
                                    </p>
                                </div>
                            ) : (
                                <>
                                    <select
                                        value={
                                            optimizeType === "pickup"
                                                ? startPickupLocation
                                                : startDropoffLocation
                                        }
                                        onChange={(e) =>
                                            handleSelectStart(e.target.value)
                                        }
                                    >
                                        {relevantLocations.map((location) => (
                                            <option
                                                key={location.address}
                                                value={location.address}
                                            >
                                                {location.address}
                                            </option>
                                        ))}
                                    </select>

                                    <div className="route-opt__start-pills">
                                        {relevantLocations.map((location) => {
                                            const isActive =
                                                (optimizeType === "pickup"
                                                    ? startPickupLocation
                                                    : startDropoffLocation) ===
                                                location.address;
                                            return (
                                                <button
                                                    key={location.address}
                                                    type="button"
                                                    className={`route-opt__pill ${isActive
                                                        ? "route-opt__pill--active"
                                                        : ""
                                                        }`}
                                                    onClick={() =>
                                                        handleSelectStart(
                                                            location.address
                                                        )
                                                    }
                                                >
                                                    <MapPin size={14} />
                                                    {location.address}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </>
                            )}
                        </div>
                    </div>

                    <div className="route-opt__locations-grid">
                        <div className="route-opt__card">
                            <div className="route-opt__card-header">
                                <Route size={18} />
                                <div>
                                    <p className="route-opt__card-label">
                                        Địa điểm đón
                                    </p>
                                    <h3>Pickup points</h3>
                                </div>
                            </div>
                            {renderLocationList(tripData.pickup_locations, "pickup")}
                        </div>

                        <div className="route-opt__card">
                            <div className="route-opt__card-header">
                                <Route size={18} />
                                <div>
                                    <p className="route-opt__card-label">
                                        Địa điểm trả
                                    </p>
                                    <h3>Dropoff points</h3>
                                </div>
                            </div>
                            {renderLocationList(
                                tripData.dropoff_locations,
                                "dropoff"
                            )}
                        </div>
                    </div>

                    <div className="route-opt__actions">
                        <div>
                            <p>AI sẽ sử dụng Gemini để sắp xếp các địa điểm.</p>
                            {lastOptimizedTrip && (
                                <small>
                                    Lần gần nhất: Trip #{lastOptimizedTrip.tripId} •{" "}
                                    {lastOptimizedTrip.optimizeType === "pickup"
                                        ? "Đón khách"
                                        : "Trả khách"}{" "}
                                    • Bắt đầu từ {lastOptimizedTrip.startedFrom}
                                </small>
                            )}
                        </div>
                        <button
                            type="button"
                            className="route-opt__primary-btn"
                            onClick={handleOptimize}
                            disabled={optimizeLoading}
                        >
                            {optimizeLoading ? "Đang tối ưu..." : "Tối ưu lộ trình"}
                        </button>
                    </div>
                    {optimizeError && (
                        <div className="route-opt__error">{optimizeError}</div>
                    )}
                </>
            )}

            {tripError && (
                <div className="route-opt__error">{tripError}</div>
            )}

            {optimizeLoading && (
                <div className="route-opt__loader">
                    <CircularIndeterminate />
                </div>
            )}

            {optimizationResult && (
                <div className="route-opt__card route-opt__card--result">
                    <div className="route-opt__card-header">
                        <Sparkles size={18} />
                        <div>
                            <p className="route-opt__card-label">Kết quả AI</p>
                            <h3>Lộ trình gợi ý</h3>
                        </div>
                    </div>
                    <div className="route-opt__result-meta">
                        <div>
                            <span>Tổng quãng đường ước tính</span>
                            <strong>
                                {optimizationResult.total_distance_estimate}
                            </strong>
                        </div>
                        <div>
                            <span>Ghi chú</span>
                            <p>{optimizationResult.reasoning || "—"}</p>
                        </div>
                    </div>

                    <div className="route-opt__timeline">
                        {optimizationResult.optimized_order?.map((item, index) => (
                            <div className="route-opt__timeline-item" key={item.id}>
                                <div className="route-opt__timeline-index">
                                    {index + 1}
                                </div>
                                <div className="route-opt__timeline-content">
                                    <p className="route-opt__timeline-address">
                                        {item.address}
                                    </p>
                                    <div className="route-opt__timeline-meta">
                                        <span>Mã: {item.id}</span>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
};

export default RouteOptimizationPage;

