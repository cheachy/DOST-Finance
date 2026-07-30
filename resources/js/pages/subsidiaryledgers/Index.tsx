import { Head, usePage, Link, router } from "@inertiajs/react";
import AppLayout from "../../layouts/AppLayout";
import MonthSelector from "../../components/MonthSelector";
import "../../../css/theme.css";
import "../../../css/dashboard.css";
import "../../../css/ledger.css";

interface Row {
    source_row: number;
    month: number | null;
    obr: string | null;
    payee: string | null;
    charging: string | null;
    rc: string | null;
    particulars: string | null;
    status: string | null;
    status_flag: string | null;
    gross: string | null;
    net: string | null;
    dv: string | null;
    payment_mode: string | null;
    pay_date: string | null;
}

interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    from: number | null;
    to: number | null;
    total: number;
}

const peso = (v: string | null) =>
    v == null
        ? ""
        : new Intl.NumberFormat("en-PH", { minimumFractionDigits: 2 }).format(
              Number(v),
          );

// map a status_flag label to a soft row tint
const flagClass = (f: string | null) => {
    if (!f) return "";
    const u = f.toUpperCase();
    if (u.includes("CANCEL")) return "row--cancelled";
    if (u.includes("NYDD") && u.includes("NOT")) return "row--nydd";
    if (u.includes("NYDD") || u.includes("BECAME")) return "row--becamedd";
    if (u.includes("DD")) return "row--dd";
    if (u.includes("UNISSUED")) return "row--unissued";
    return "";
};

const TAB_LABELS: Record<string, string> = {
    PS: "Personal Services",
    MOOE: "Maintenance & Other Operating Expenses",
    GIA: "Grants-in-Aid",
};

export default function Index() {
    const page = usePage().props as any;
    const { auth, hasLedger, snapshot, tabs, tab, months, month, rows } =
        page;
    if (!auth?.user) return null;

    const paginator: Paginator<Row> | null = rows;

    function goTab(next: string) {
        router.get(
            "/subsidiary-ledgers",
            { tab: next },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <AppLayout user={auth.user} current="subsidiary">
            <Head title="Subsidiary ledgers" />

            <header className="dash-topbar">
                <div>
                    <h1 className="dash-topbar__title">
                        Subsidiary ledgers
                    </h1>
                    <p className="dash-topbar__sub">
                        {hasLedger
                            ? `${snapshot.original_name} · generated from the general ledger, read-only`
                            : "No ledger imported yet."}
                    </p>
                </div>
            </header>

            <div className="dash-body">
                {!hasLedger ? (
                    <section className="dash-card">
                        <div className="dash-empty">
                            <p>
                                Import a workbook from the dashboard to view
                                subsidiary ledgers.
                            </p>
                        </div>
                    </section>
                ) : (
                    <>
                        <div
                            className="month-selector"
                            role="tablist"
                            aria-label="Select subsidiary ledger"
                        >
                            {tabs.map((t: string) => (
                                <button
                                    key={t}
                                    type="button"
                                    className={`month-chip${tab === t ? " is-active" : ""}`}
                                    onClick={() => goTab(t)}
                                    title={TAB_LABELS[t] ?? t}
                                >
                                    {t}
                                </button>
                            ))}
                        </div>

                        <MonthSelector
                            months={months}
                            active={month}
                            basePath="/subsidiary-ledgers"
                            extraParams={{ tab }}
                        />

                        <div className="ledger-scroll">
                            <table className="ledger-table">
                                <thead>
                                    <tr>
                                        <th className="num">Row</th>
                                        <th>OBR</th>
                                        <th>Payee</th>
                                        <th>Charging</th>
                                        <th>RC</th>
                                        <th>Particulars</th>
                                        <th>W</th>
                                        <th className="num">Gross</th>
                                        <th className="num">Net</th>
                                        <th>DV</th>
                                        <th>A/C</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {paginator?.data.map((r) => (
                                        <tr
                                            key={r.source_row}
                                            className={flagClass(
                                                r.status_flag,
                                            )}
                                            title={r.status_flag ?? undefined}
                                        >
                                            <td className="num muted">
                                                {r.source_row}
                                            </td>
                                            <td>{r.obr}</td>
                                            <td className="ellip">
                                                {r.payee}
                                            </td>
                                            <td>{r.charging}</td>
                                            <td>{r.rc}</td>
                                            <td className="ellip">
                                                {r.particulars}
                                            </td>
                                            <td>{r.status}</td>
                                            <td className="num">
                                                {peso(r.gross)}
                                            </td>
                                            <td className="num">
                                                {peso(r.net)}
                                            </td>
                                            <td className="ellip">{r.dv}</td>
                                            <td className="center">
                                                {r.payment_mode}
                                            </td>
                                            <td className="muted">
                                                {r.pay_date}
                                            </td>
                                        </tr>
                                    ))}
                                    {paginator?.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={11}
                                                className="muted"
                                                style={{
                                                    textAlign: "center",
                                                    padding: "24px",
                                                }}
                                            >
                                                No {tab} transactions for this
                                                period.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {paginator && (
                            <div className="ledger-pager">
                                <span className="muted">
                                    {paginator.from}–{paginator.to} of{" "}
                                    {paginator.total.toLocaleString()}
                                </span>
                                <div className="ledger-pager__links">
                                    {paginator.links.map((l, i) =>
                                        l.url ? (
                                            <Link
                                                key={i}
                                                href={l.url}
                                                preserveScroll
                                                className={`ledger-pager__link${l.active ? " is-active" : ""}`}
                                                dangerouslySetInnerHTML={{
                                                    __html: l.label,
                                                }}
                                            />
                                        ) : (
                                            <span
                                                key={i}
                                                className="ledger-pager__link is-disabled"
                                                dangerouslySetInnerHTML={{
                                                    __html: l.label,
                                                }}
                                            />
                                        ),
                                    )}
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
