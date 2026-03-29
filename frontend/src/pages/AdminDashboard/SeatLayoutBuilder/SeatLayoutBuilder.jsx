import { useEffect, useMemo, useState } from "react";
import { useSearchParams } from "react-router-dom";
import { DndContext } from "@dnd-kit/core";
import { toast } from "react-toastify";
import axiosClient from "../../../apis/axiosClient";
import SeatNode from "./components/SeatNode";
import useBusData from "./hooks/useBusData";
import useSeatLayout from "./hooks/useSeatLayout";
import {
    clamp,
    buildClientSeat,
} from "./utils/seatLayoutUtils";
import "./SeatLayoutBuilder.scss";





export default function SeatLayoutBuilder() {
    const [searchParams] = useSearchParams();
    const urlBusId = searchParams.get("busId");

    const { buses, selectedBusId, setSelectedBusId } = useBusData(urlBusId);

    const {
        layout,
        setLayout,
        seats,
        setSeats,
        activeDeck,
        setActiveDeck,
        loading,
        activeSeat,
        setActiveSeat,
        selectedSeat,
    } = useSeatLayout(selectedBusId);

    const [seatSearch, setSeatSearch] = useState("");
    const [saving, setSaving] = useState(false);
    const [deleting, setDeleting] = useState(false);

    const visibleSeats = useMemo(
        () => seats.filter((seat) => seat.deck === activeDeck),
        [seats, activeDeck]
    );



    const filteredSeats = useMemo(() => {
        const keyword = seatSearch.trim().toLowerCase();
        return seats
            .filter((seat) => seat.deck === activeDeck)
            .filter((seat) =>
                keyword ? seat.label.toLowerCase().includes(keyword) : true
            )
            .sort((a, b) => a.label.localeCompare(b.label, "vi", { numeric: true }));
    }, [seats, activeDeck, seatSearch]);

    const snapToGrid = (value) =>
        Math.round(value / layout.cell_size) * layout.cell_size;

    const handleDragEnd = (event) => {
        const { active, delta } = event;
        if (!active?.id) return;
        setSeats((prev) =>
            prev.map((seat) => {
                if (seat.clientId !== active.id) return seat;
                const nextX = seat.position.x + delta.x;
                const nextY = seat.position.y + delta.y;
                const canvasWidth = layout.canvas.width - seat.position.w;
                const canvasHeight = layout.canvas.height - seat.position.h;
                return {
                    ...seat,
                    position: {
                        ...seat.position,
                        x: clamp(snapToGrid(nextX), 0, canvasWidth),
                        y: clamp(snapToGrid(nextY), 0, canvasHeight),
                    },
                };
            })
        );
    };

    const handleSeatFieldChange = (field, value) => {
        if (!selectedSeat) return;
        setSeats((prev) =>
            prev.map((seat) =>
                seat.clientId === selectedSeat.clientId
                    ? {
                        ...seat,
                        [field]:
                            field === "deck" || field === "index"
                                ? Number(value)
                                : value,
                    }
                    : seat
            )
        );
        if (field === "deck") {
            setActiveDeck(Number(value) || 1);
        }
    };

    const handleSeatPositionChange = (field, value) => {
        if (!selectedSeat) return;
        const numeric = Number(value) || 0;
        const safeValue =
            field === "w" || field === "h"
                ? Math.max(24, numeric)
                : Math.max(0, numeric);
        setSeats((prev) =>
            prev.map((seat) =>
                seat.clientId === selectedSeat.clientId
                    ? {
                        ...seat,
                        position: {
                            ...seat.position,
                            [field]: safeValue,
                        },
                    }
                    : seat
            )
        );
    };

    const handleAddSeat = () => {
        const newSeat = buildClientSeat({
            label: `N${seats.length + 1}`,
            deck: activeDeck,
            position: { x: 20, y: 20, w: 40, h: 40 },
            column_group: "A",
            index: seats.length,
        });
        setSeats((prev) => [...prev, newSeat]);
        setActiveSeat(newSeat.clientId);
    };

    const handleDeleteSeat = async () => {
        if (!selectedSeat) return;
        const confirmed = window.confirm(
            `Bạn có chắc muốn xoá ghế ${selectedSeat.label}?`
        );
        if (!confirmed) return;

        try {
            setDeleting(true);
            // Nếu ghế đã có seat_id thì gọi API xoá trước
            if (selectedSeat.seat_id && selectedBusId) {
                await axiosClient.delete(
                    `/admin/buses/${selectedBusId}/seat-layout/${selectedSeat.seat_id}`
                );
            }
            setSeats((prev) =>
                prev.filter((seat) => seat.clientId !== selectedSeat.clientId)
            );
            setActiveSeat(null);
            toast.success("Đã xoá ghế khỏi sơ đồ");
        } catch (error) {
            console.error(error);
            toast.error(
                error?.response?.data?.message || "Không thể xoá ghế, vui lòng thử lại"
            );
        } finally {
            setDeleting(false);
        }
    };

    const handleSave = async () => {
        if (!selectedBusId) return;
        setSaving(true);
        try {
            const payload = {
                layout,
                seats: seats.map((seat, index) => ({
                    seat_id: seat.seat_id,
                    label: seat.label,
                    deck: seat.deck,
                    column_group: seat.column_group,
                    index: seat.index ?? index,
                    seat_type: seat.seat_type,
                    active: seat.active,
                    position: seat.position,
                    meta: seat.meta,
                })),
            };
            await axiosClient.put(
                `/admin/buses/${selectedBusId}/seat-layout`,
                payload
            );
            toast.success("Đã lưu sơ đồ ghế");
        } catch (error) {
            toast.error(
                error?.response?.data?.message || "Lưu sơ đồ ghế thất bại"
            );
        } finally {
            setSaving(false);
        }
    };

    const decks = useMemo(
        () => Array.from({ length: layout.decks || 1 }, (_, i) => i + 1),
        [layout.decks]
    );

    return (
        <div className="seat-builder">
            <header className="seat-builder__header">
                <div className="seat-builder__field">
                    <label>Chọn xe</label>
                    <select
                        value={selectedBusId}
                        onChange={(e) => setSelectedBusId(e.target.value)}
                        disabled={!buses.length}
                    >
                        {buses.length === 0 ? (
                            <option>Chưa có dữ liệu xe</option>
                        ) : (
                            buses.map((bus) => (
                                <option key={bus.id} value={bus.id}>
                                    {bus.name} - {bus.plate_number}
                                </option>
                            ))
                        )}
                    </select>
                </div>
                <div className="seat-builder__actions">
                    <button
                        className="seat-builder__button"
                        onClick={handleAddSeat}
                    >
                        Thêm ghế
                    </button>
                    <button
                        className="seat-builder__button seat-builder__button--primary"
                        onClick={handleSave}
                        disabled={saving || loading}
                    >
                        {saving ? "Đang lưu..." : "Lưu sơ đồ"}
                    </button>
                </div>
            </header>

            <div className="seat-builder__body">
                <aside className="seat-builder__sidebar">
                    <div className="seat-builder__panel">
                        <div className="seat-builder__panel-header">
                            <h3>Danh sách ghế</h3>
                            <span className="seat-builder__badge">
                                {filteredSeats.length}/{visibleSeats.length}
                            </span>
                        </div>
                        <div className="seat-builder__field">
                            <input
                                type="text"
                                placeholder="Tìm theo nhãn ghế..."
                                value={seatSearch}
                                onChange={(e) => setSeatSearch(e.target.value)}
                            />
                        </div>
                        <div className="seat-builder__seat-list">
                            {filteredSeats.length === 0 ? (
                                <p className="seat-builder__empty">
                                    Không tìm thấy ghế
                                </p>
                            ) : (
                                filteredSeats.map((seat) => (
                                    <button
                                        key={seat.clientId}
                                        type="button"
                                        className={`seat-builder__seat-item ${activeSeat === seat.clientId
                                            ? "seat-builder__seat-item--active"
                                            : ""
                                            }`}
                                        onClick={() => {
                                            setActiveDeck(seat.deck);
                                            setActiveSeat(seat.clientId);
                                        }}
                                    >
                                        <span className="seat-builder__seat-label">
                                            {seat.label}
                                        </span>
                                        <span className="seat-builder__seat-meta">
                                            Tầng {seat.deck}
                                        </span>
                                    </button>
                                ))
                            )}
                        </div>
                    </div>

                    <div className="seat-builder__panel">
                        <div className="seat-builder__panel-header">
                            <h3>Ghế đang chọn</h3>
                            {selectedSeat && (
                                <button
                                    className="seat-builder__button seat-builder__button--danger"
                                    onClick={handleDeleteSeat}
                                    disabled={deleting}
                                >
                                    {deleting ? "Đang xoá..." : "Xoá"}
                                </button>
                            )}
                        </div>
                        {selectedSeat ? (
                            <div className="seat-builder__form">
                                <div className="seat-builder__field">
                                    <label>Nhãn ghế</label>
                                    <input
                                        type="text"
                                        value={selectedSeat.label}
                                        onChange={(e) =>
                                            handleSeatFieldChange(
                                                "label",
                                                e.target.value.toUpperCase()
                                            )
                                        }
                                    />
                                </div>
                                <div className="seat-builder__field">
                                    <label>Tầng</label>
                                    <select
                                        value={selectedSeat.deck}
                                        onChange={(e) =>
                                            handleSeatFieldChange(
                                                "deck",
                                                e.target.value
                                            )
                                        }
                                    >
                                        {decks.map((deck) => (
                                            <option key={deck} value={deck}>
                                                Tầng {deck}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="seat-builder__field">
                                    <label>Nhóm cột</label>
                                    <input
                                        type="text"
                                        value={selectedSeat.column_group}
                                        onChange={(e) =>
                                            handleSeatFieldChange(
                                                "column_group",
                                                e.target.value.toUpperCase()
                                            )
                                        }
                                    />
                                </div>
                                <div className="seat-builder__field">
                                    <label>Thứ tự trong cột</label>
                                    <input
                                        type="number"
                                        min="0"
                                        value={selectedSeat.index}
                                        onChange={(e) =>
                                            handleSeatFieldChange(
                                                "index",
                                                e.target.value
                                            )
                                        }
                                    />
                                </div>
                                <div className="seat-builder__field">
                                    <label>Loại ghế</label>
                                    <input
                                        type="text"
                                        value={selectedSeat.seat_type}
                                        onChange={(e) =>
                                            handleSeatFieldChange(
                                                "seat_type",
                                                e.target.value
                                            )
                                        }
                                    />
                                </div>
                                <div className="seat-builder__field-grid">
                                    <div>
                                        <label>X (px)</label>
                                        <input
                                            type="number"
                                            value={selectedSeat.position.x}
                                            onChange={(e) =>
                                                handleSeatPositionChange(
                                                    "x",
                                                    e.target.value
                                                )
                                            }
                                        />
                                    </div>
                                    <div>
                                        <label>Y (px)</label>
                                        <input
                                            type="number"
                                            value={selectedSeat.position.y}
                                            onChange={(e) =>
                                                handleSeatPositionChange(
                                                    "y",
                                                    e.target.value
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                                <div className="seat-builder__field-grid">
                                    <div>
                                        <label>Rộng (px)</label>
                                        <input
                                            type="number"
                                            value={selectedSeat.position.w}
                                            onChange={(e) =>
                                                handleSeatPositionChange(
                                                    "w",
                                                    e.target.value
                                                )
                                            }
                                        />
                                    </div>
                                    <div>
                                        <label>Cao (px)</label>
                                        <input
                                            type="number"
                                            value={selectedSeat.position.h}
                                            onChange={(e) =>
                                                handleSeatPositionChange(
                                                    "h",
                                                    e.target.value
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <p className="seat-builder__empty">
                                Chọn ghế trên canvas để chỉnh sửa
                            </p>
                        )}
                    </div>

                    <div className="seat-builder__panel">
                        <h3>Thông số bố cục</h3>
                        <div className="seat-builder__field">
                            <label>Số tầng</label>
                            <input
                                type="number"
                                min="1"
                                max="4"
                                value={layout.decks}
                                onChange={(e) =>
                                    setLayout((prev) => ({
                                        ...prev,
                                        decks: Number(e.target.value) || 1,
                                    }))
                                }
                            />
                        </div>
                        <div className="seat-builder__field">
                            <label>Chiều rộng canvas(px)</label>
                            <input
                                type="number"
                                min="200"
                                max="2000"
                                value={layout.canvas.width}
                                onChange={(e) =>
                                    setLayout((prev) => ({
                                        ...prev,
                                        canvas: {
                                            ...prev.canvas,
                                            width: Number(e.target.value) || 200,
                                        },
                                    }))
                                }
                            />
                        </div>
                        <div className="seat-builder__field">
                            <label>Chiều cao canvas(px)</label>
                            <input
                                type="number"
                                min="200"
                                max="2000"
                                value={layout.canvas.height}
                                onChange={(e) =>
                                    setLayout((prev) => ({
                                        ...prev,
                                        canvas: {
                                            ...prev.canvas,
                                            height:
                                                Number(e.target.value) || 200,
                                        },
                                    }))
                                }
                            />
                        </div>
                    </div>
                </aside>

                <section className="seat-builder__canvas-wrapper">
                    <div className="seat-builder__deck-tabs">
                        {decks.map((deck) => (
                            <button
                                key={deck}
                                className={`seat-builder__deck-tab ${activeDeck === deck
                                    ? "seat-builder__deck-tab--active"
                                    : ""
                                    }`}
                                onClick={() => setActiveDeck(deck)}
                            >
                                Tầng {deck}
                            </button>
                        ))}
                    </div>

                    <div className="seat-builder__canvas-meta">
                        <span>
                            {visibleSeats.length} ghế (tổng {seats.length})
                        </span>
                        <span>Kéo thả ghế để sắp xếp vị trí</span>
                    </div>

                    <DndContext onDragEnd={handleDragEnd}>
                        <div
                            className="seat-builder__canvas"
                            style={{
                                width: layout.canvas.width,
                                height: layout.canvas.height,
                                backgroundImage:
                                    "linear-gradient(to right, #e2e8f0 1px, transparent 1px), linear-gradient(to bottom, #e2e8f0 1px, transparent 1px)",
                                backgroundSize: `${layout.cell_size}px ${layout.cell_size}px`,
                            }}
                            onClick={() => setActiveSeat(null)}
                        >
                            {loading && (
                                <div className="seat-builder__canvas-loading">
                                    Đang tải sơ đồ...
                                </div>
                            )}

                            {!loading &&
                                visibleSeats.map((seat) => (
                                    <SeatNode
                                        key={seat.clientId}
                                        seat={seat}
                                        isActive={
                                            activeSeat === seat.clientId
                                        }
                                        onSelect={setActiveSeat}
                                    />
                                ))}
                        </div>
                    </DndContext>
                </section>
            </div>
        </div>
    );
}

