import { useState, useEffect, useMemo } from "react";
import { toast } from "react-toastify";
import axiosClient from "../../../../apis/axiosClient";
import { applyLayoutDefaults, buildClientSeat } from "../utils/seatLayoutUtils";

export default function useSeatLayout(selectedBusId) {
    const [layout, setLayout] = useState(applyLayoutDefaults());
    const [seats, setSeats] = useState([]);
    const [activeDeck, setActiveDeck] = useState(1);
    const [loading, setLoading] = useState(false);
    const [activeSeat, setActiveSeat] = useState(null);

    useEffect(() => {
        if (!selectedBusId) return;
        async function loadLayout() {
            setLoading(true);
            setActiveSeat(null);
            try {
                const { data } = await axiosClient.get(
                    `/admin/buses/${selectedBusId}/seat-layout`,
                );
                if (data?.data) {
                    setLayout(applyLayoutDefaults(data.data.layout));
                    setSeats(
                        (data.data.seats || []).map((seat) =>
                            buildClientSeat(seat),
                        ),
                    );
                    setActiveDeck(1);
                }
            } catch (e) {
                console.error(e);
                toast.error("Không thể tải sơ đồ ghế");
            } finally {
                setLoading(false);
            }
        }
        loadLayout();
    }, [selectedBusId]);

    const selectedSeat = useMemo(
        () => seats.find((seat) => seat.clientId === activeSeat),
        [seats, activeSeat],
    );

    return {
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
    };
}
