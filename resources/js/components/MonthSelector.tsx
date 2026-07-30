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
    basePath: string; // e.g. '/ledger'
}

/**
 * URL-driven period selector. Selection lives in ?month= so views are
 * shareable, survive refresh, and keep the back button meaningful. An Inertia
 * partial reload keeps scroll position and only refetches the page's data.
 */
export default function MonthSelector({
    months,
    active,
    basePath,
}: MonthSelectorProps) {
    function go(month: number | null) {
        router.get(basePath, month ? { month } : {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    return (
        <div
            className="month-selector"
            role="tablist"
            aria-label="Select month"
        >
            <button
                type="button"
                className={`month-chip${active === null ? " is-active" : ""}`}
                onClick={() => go(null)}
            >
                All
            </button>
            {NAMES.map((name, i) => {
                const m = i + 1;
                const present = months.includes(m);
                return (
                    <button
                        key={name}
                        type="button"
                        className={`month-chip${active === m ? " is-active" : ""}`}
                        disabled={!present}
                        onClick={() => present && go(m)}
                        title={present ? undefined : "No data for this month"}
                    >
                        {name}
                    </button>
                );
            })}
        </div>
    );
}
