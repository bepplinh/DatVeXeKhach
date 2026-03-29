import { useState, useEffect } from "react";
import { toast } from "react-toastify";
import axiosClient from "../../../../apis/axiosClient";

export default function useBusData(urlBusId) {
    const [buses, setBuses] = useState([]);
    const [selectedBusId, setSelectedBusId] = useState("");

    useEffect(() => {
        async function loadBuses() {
            try {
                const { data } = await axiosClient.get("/buses", {
                    params: { per_page: 100 },
                });
                const list = data?.data?.data ?? [];
                setBuses(list);
                // If busId from URL, select it; otherwise select first bus
                if (
                    urlBusId &&
                    list.some((b) => String(b.id) === String(urlBusId))
                ) {
                    setSelectedBusId(urlBusId);
                } else if (list.length && !selectedBusId) {
                    setSelectedBusId(list[0].id);
                }
            } catch (e) {
                console.error(e);
                toast.error("Không thể tải danh sách xe");
            }
        }
        loadBuses();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [urlBusId]);

    return { buses, selectedBusId, setSelectedBusId };
}
