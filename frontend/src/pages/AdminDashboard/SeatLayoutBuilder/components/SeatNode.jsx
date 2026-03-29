import { useDraggable } from "@dnd-kit/core";
import { CSS } from "@dnd-kit/utilities";

export default function SeatNode({ seat, isActive, onSelect }) {
    const { attributes, listeners, setNodeRef, transform } = useDraggable({
        id: seat.clientId,
        activationConstraint: { distance: 6 }, // prevent accidental drag on click
    });

    const style = {
        width: seat.position.w,
        height: seat.position.h,
        transform: transform
            ? CSS.Translate.toString(transform)
            : undefined,
        left: seat.position.x,
        top: seat.position.y,
    };

    return (
        <button
            type="button"
            ref={setNodeRef}
            style={style}
            className={`seat-builder__seat ${isActive ? "seat-builder__seat--active" : ""}`}
            onClick={(e) => {
                e.stopPropagation();
                onSelect(seat.clientId);
            }}
            {...listeners}
            {...attributes}
        >
            <span>{seat.label}</span>
        </button>
    );
}
