import { router } from "@inertiajs/react";

const NAMES = [
    "Jan",
    "Feb",
    "Mar",
    "Apr",
    "May",
    "Jun",
    "Jul",
    "Aug",
    "Sep",
    "Oct",
    "Nov",
    "Dec",
];

interface MonthSelectorProps {
    months: number[]; // months present in the snapshot (1-12)
    active: number | null; // null = all / year-to-date
    basePath: string;
    extraParams?: Record<string, string | number>; // other URL state to preserve, e.g. { tab: 'PS' }
    variant?: "chip" | "grid"; // "chip" = pill row (default), "grid" = boxed dashboard style
}

/*
    URL-driven period selector.
 */
export default function MonthSelector({
    months,
    active,
    basePath,
    extraParams = {},
    variant = "chip",
}: MonthSelectorProps) {
    function go(month: number | null) {
        router.get(
            basePath,
            month ? { ...extraParams, month } : { ...extraParams },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    const isGrid = variant === "grid";
    const containerClass = isGrid ? "dash-months" : "month-selector";
    const itemClass = isGrid ? "dash-month" : "month-chip";

    const allButton = (
        <button
            type="button"
            className={`${itemClass}${isGrid ? " is-present" : ""}${active === null ? " is-active" : ""}`}
            onClick={() => go(null)}
        >
            All
        </button>
    );

    const monthButtons = NAMES.map((name, i) => {
        const m = i + 1;
        const present = months.includes(m);
        return (
            <button
                key={name}
                type="button"
                className={`${itemClass}${present ? " is-present" : ""}${active === m ? " is-active" : ""}`}
                disabled={!present}
                onClick={() => present && go(m)}
                title={present ? undefined : "No data for this month"}
            >
                {name}
            </button>
        );
    });

    return (
        <div
            className={containerClass}
            role="tablist"
            aria-label="Select month"
        >
            {allButton}
            {monthButtons}
        </div>
    );
}
