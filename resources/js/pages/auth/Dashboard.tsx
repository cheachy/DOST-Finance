import { Head, Link, usePage, router } from "@inertiajs/react";
import { useRef, useState, useEffect, ChangeEvent, DragEvent } from "react";
import AppLayout from "../../layouts/AppLayout";
import MonthSelector from "../../components/MonthSelector";
import MonthlyTrendChart from "../../components/MonthlyTrendChart";
import "../../../css/theme.css";
import "../../../css/dashboard.css";
import "../../../css/ledger.css";
import LiveMeta from "../../components/LiveMeta";

interface Snapshot {
    id: number;
    original_name: string;
    fiscal_year: number;
    imported_at: string;
    transaction_count: number;
    months: number[];
}

interface Stats {
    allotted: number;
    disbursed: number;
    balance: number;
    utilization: number;
}

interface MonthTrendPoint {
    month: number;
    allotted: number;
    disbursed: number;
}

interface Activity {
    id: number;
    level: "success" | "info" | "error";
    title: string;
    detail: string;
    at: string;
}

interface Alerts {
    unrouted: number;
    stale: boolean;
}

const MONTH_NAMES = [
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

const peso = (n: number) =>
    new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
    }).format(n || 0);

export default function Dashboard() {
    const page = usePage().props as any;
    const { auth, status, errors } = page;

    // all optional — the page renders correctly before the backend supplies them
    const snapshot: Snapshot | null = page.snapshot ?? null;
    const month: number | null = page.month ?? null;
    const stats: Stats = page.stats ?? {
        allotted: 0,
        disbursed: 0,
        balance: 0,
        utilization: 0,
    };
    const monthlyTrend: MonthTrendPoint[] = page.monthlyTrend ?? [];
    const activity: Activity[] = page.activity ?? [];
    const alerts: Alerts = page.alerts ?? { unrouted: 0, stale: false };

    const fileInputRef = useRef<HTMLInputElement>(null);
    const [processing, setProcessing] = useState(false);
    const [dragging, setDragging] = useState(false);

    const activityListRef = useRef<HTMLUListElement>(null);
    const [visibleActivityCount, setVisibleActivityCount] = useState(activity.length);

    useEffect(() => {
        const measure = () => {
            if (!activityListRef.current) return;
            const containerHeight = activityListRef.current.clientHeight;
            const firstTicket = activityListRef.current.firstElementChild;
            if (!firstTicket) return;
            const ticketHeight = firstTicket.getBoundingClientRect().height;
            if (ticketHeight === 0) return;
            const gap = 12;
            const count = Math.max(1, Math.floor((containerHeight + gap) / (ticketHeight + gap)));
            setVisibleActivityCount(Math.min(count, activity.length));
        };

        const el = activityListRef.current;
        if (!el) return;

        const observer = new ResizeObserver(() => measure());
        observer.observe(el);

        return () => observer.disconnect();
    }, [activity]);

    function upload(file: File, reset?: () => void) {
        setProcessing(true);
        router.post(
            "/imports",
            { file, year: new Date().getFullYear() },
            {
                forceFormData: true,
                onFinish: () => {
                    setProcessing(false);
                    reset?.();
                },
            },
        );
    }

    function handleFileChange(e: ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;
        upload(file, () => {
            e.target.value = ""; // allow re-selecting the same file later
        });
    }

    function handleDrop(e: DragEvent<HTMLDivElement>) {
        e.preventDefault();
        setDragging(false);
        if (processing) return;
        const file = e.dataTransfer.files?.[0];
        if (file && /\.xlsx?$/i.test(file.name)) upload(file);
    }

    if (!auth?.user) return null;

    const hasLedger = Boolean(snapshot);
    const latestMonth = snapshot?.months.length
        ? Math.max(...snapshot.months)
        : null;
    const importError = errors?.file || errors?.year || errors?.upload;

    return (
        <AppLayout user={auth.user} current="dashboard">
            <Head title="Dashboard" />

            <header className="dash-topbar">
                <div>
                    <h1 className="dash-topbar__title">Financial overview</h1>
                    <p className="dash-topbar__sub">
                        Allotment, disbursement, and fund utilization across the
                        active ledger.
                    </p>
                </div>
                <div className="dash-topbar__meta">
                    <span>Butuan City, PH</span>
                    <LiveMeta />
                </div>
            </header>

            <div className="dash-body">
                {status && (
                    <p className="dash-flash dash-flash--ok">{status}</p>
                )}
                {importError && (
                    <p className="dash-flash dash-flash--err">{importError}</p>
                )}

                {alerts.unrouted > 0 && (
                    <p className="dash-flash dash-flash--warn">
                        {alerts.unrouted} transaction
                        {alerts.unrouted === 1 ? "" : "s"} could not be routed
                        to a subsidiary ledger. Review the charging codes before
                        generating reports.
                    </p>
                )}

                {/* ---- import: the primary action when there is no ledger yet ---- */}
                {!hasLedger ? (
                    <section
                        className={`dash-import${dragging ? " is-dragging" : ""}`}
                        onDragOver={(e) => {
                            e.preventDefault();
                            setDragging(true);
                        }}
                        onDragLeave={() => setDragging(false)}
                        onDrop={handleDrop}
                    >
                        <h2>Import your general ledger</h2>
                        <p>
                            Upload your workbook to parse transactions, generate
                            subsidiary ledgers, and build the monthly reports of
                            disbursement.
                        </p>
                        <button
                            type="button"
                            className="dash-btn dash-btn--primary"
                            disabled={processing}
                            onClick={() => fileInputRef.current?.click()}
                        >
                            {processing ? "Processing…" : "Select Excel file"}
                        </button>
                        <p className="dash-import__hint">
                            or drop the spreadsheet here
                        </p>
                    </section>
                ) : (
                    <section className="dash-snapshot">
                        <div className="dash-snapshot__info">
                            <span className="dash-snapshot__label">
                                Active ledger
                            </span>
                            <span className="dash-snapshot__name">
                                {snapshot!.original_name}
                            </span>
                            <span className="dash-snapshot__meta">
                                FY {snapshot!.fiscal_year} ·{" "}
                                {snapshot!.transaction_count.toLocaleString()}{" "}
                                transactions · imported {snapshot!.imported_at}
                            </span>
                        </div>
                        <button
                            type="button"
                            className="dash-btn"
                            disabled={processing}
                            onClick={() => fileInputRef.current?.click()}
                        >
                            {processing ? "Processing…" : "Re-import workbook"}
                        </button>
                    </section>
                )}

                <input
                    ref={fileInputRef}
                    type="file"
                    accept=".xlsx,.xls"
                    onChange={handleFileChange}
                    style={{ display: "none" }}
                />

                {/* ---- metrics ---- */}
                <div className="dash-metrics">
                    <article className="dash-metric">
                        <span className="dash-metric__label">
                            Total funds allotted
                        </span>
                        <span className="dash-metric__value">
                            {peso(stats.allotted)}
                        </span>
                        <span className="dash-tag">
                            {hasLedger ? "From NCA receipts" : "No data"}
                        </span>
                    </article>

                    <article className="dash-metric">
                        <span className="dash-metric__label">
                            Total disbursements
                        </span>
                        <span className="dash-metric__value">
                            {peso(stats.disbursed)}
                        </span>
                        <span className="dash-tag">
                            {hasLedger ? "Paid via ADA / check" : "No data"}
                        </span>
                    </article>

                    <article className="dash-metric">
                        <span className="dash-metric__label">
                            Unexpended balance
                        </span>
                        <span className="dash-metric__value">
                            {peso(stats.balance)}
                        </span>
                        <span className="dash-tag">
                            {hasLedger ? "Computed" : "No data"}
                        </span>
                    </article>

                    <article className="dash-metric">
                        <span className="dash-metric__label">
                            Fund utilization
                        </span>
                        <span className="dash-metric__value">
                            {(stats.utilization || 0).toFixed(2)}%
                        </span>
                        <span className="dash-meter" aria-hidden="true">
                            <span
                                className="dash-meter__fill"
                                style={{
                                    width: `${Math.min(100, Math.max(0, stats.utilization || 0))}%`,
                                }}
                            />
                        </span>
                    </article>
                </div>

                {/* ---- coverage + trend + activity ---- */}
                <div className="dash-columns">
                    <div className="dash-columns__left">
                        <section className="dash-card dash-card--compact">
                            <div className="dash-card__head">
                                <h2>Month coverage</h2>
                                <span className="dash-card__note">
                                    {hasLedger
                                        ? month
                                            ? `As of ${MONTH_NAMES[month - 1]}`
                                            : latestMonth
                                              ? `As of ${MONTH_NAMES[latestMonth - 1]}`
                                              : ""
                                        : "Awaiting import"}
                                </span>
                            </div>

                            {hasLedger ? (
                                <MonthSelector
                                    months={snapshot!.months}
                                    active={month}
                                    basePath="/dashboard"
                                    variant="grid"
                                />
                            ) : (
                                <div className="dash-empty">
                                    <p>
                                        Import a workbook to see which months
                                        are covered.
                                    </p>
                                </div>
                            )}
                        </section>

                        <section className="dash-card dash-chart-card">
                            <div className="dash-card__head">
                                <h2>Monthly Allotment vs Disbursement</h2>
                                <span className="dash-card__note">
                                    {hasLedger
                                        ? latestMonth
                                            ? `As of ${MONTH_NAMES[latestMonth - 1]}`
                                            : ""
                                        : "Awaiting import"}
                                </span>
                            </div>
                            {hasLedger && monthlyTrend.length > 0 ? (
                                <MonthlyTrendChart data={monthlyTrend} />
                            ) : (
                                <div className="dash-empty">
                                    <p>
                                        Import a workbook to see the monthly
                                        trend.
                                    </p>
                                </div>
                            )}
                        </section>
                    </div>

                    <section className="dash-card dash-card--recent">
                        <div className="dash-card__head">
                            <h2>Recent activity</h2>
                            <Link href="/logs" className="dash-card__link">
                                View all
                            </Link>
                        </div>

                        {activity.length > 0 ? (
                            <ul className="dash-activity" ref={activityListRef}>
                                {activity.slice(0, visibleActivityCount).map((a) => (
                                    <li key={a.id} className={`dash-activity__ticket dash-activity__ticket--${a.level || 'info'}`}>
                                        <div className="dash-activity__ticket-content">
                                            <span className="dash-activity__title">
                                                {a.title}
                                            </span>
                                            {a.detail && (
                                                <span className="dash-activity__detail">
                                                    {a.detail}
                                                </span>
                                            )}
                                        </div>
                                        <div className="dash-activity__ticket-meta">
                                            <span className="dash-activity__at">
                                                {a.at}
                                            </span>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <div className="dash-empty">
                                <p>No activity yet.</p>
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
