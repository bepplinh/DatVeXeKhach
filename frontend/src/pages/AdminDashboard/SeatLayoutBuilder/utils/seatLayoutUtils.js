export const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

export const DEFAULT_LEGEND = [
    { label: "Ghế trống", color: "#E0E7FF" },
    { label: "Đang giữ", color: "#FDE68A" },
    { label: "Đã bán", color: "#FCA5A5" },
];

export const applyLayoutDefaults = (layout = {}) => ({
    decks: layout.decks ?? 1,
    cell_size: layout.cell_size ?? 40,
    canvas: {
        width: layout.canvas?.width ?? 720,
        height: layout.canvas?.height ?? 480,
    },
    legend: layout.legend ?? DEFAULT_LEGEND,
});

export const createClientId = () =>
    globalThis.crypto?.randomUUID?.() ?? Math.random().toString(36).slice(2);

export const normalizeClientId = (seat) =>
    seat?.seat_id !== undefined && seat?.seat_id !== null
        ? `seat-${seat.seat_id}`
        : createClientId();

export const buildClientSeat = (seat) => ({
    clientId: normalizeClientId(seat),
    seat_id: seat?.seat_id ?? null,
    label: seat?.label ?? "NEW",
    deck: seat?.deck ?? 1,
    column_group: seat?.column_group ?? "A",
    index: seat?.index ?? 0,
    seat_type: seat?.seat_type ?? "standard",
    active: seat?.active ?? true,
    position: {
        x: seat?.position?.x ?? 20,
        y: seat?.position?.y ?? 20,
        w: seat?.position?.w ?? 40,
        h: seat?.position?.h ?? 40,
    },
    meta: seat?.meta ?? {},
});
