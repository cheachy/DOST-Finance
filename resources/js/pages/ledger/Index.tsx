import { Head, usePage, Link } from "@inertiajs/react";
import AppLayout from "../../layouts/AppLayout";
import MonthSelector from "../../components/MonthSelector";
import "../../../css/theme.css";
import "../../../css/dashboard.css";
import "../../../css/ledger.css";

interface Row {
    source_row: number;
    row_type: string;
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

export default function Index() {
    const page = usePage().props as any;
    const { auth, hasLedger, snapshot, months, month, rows } = page;
    if (!auth?.user) return null;

    const paginator: Paginator<Row> | null = rows;

    return (
        <AppLayout user={auth.user} current="ledger">
            <Head title="General ledger" />

            <header className="dash-topbar">
                <div>
                    <h1 className="dash-topbar__title">General ledger</h1>
                    <p className="dash-topbar__sub">
                        {hasLedger
                            ? `${snapshot.original_name} · ${snapshot.transaction_count.toLocaleString()} transactions`
                            : "No ledger imported yet."}
                    </p>
                </div>
            </header>

            <div className="dash-body">
                {!hasLedger ? (
                    <section className="dash-card">
                        <div className="dash-empty">
                            <p>
                                Import a workbook from the dashboard to view the
                                ledger.
                            </p>
                        </div>
                    </section>
                ) : (
                    <>
                        <MonthSelector
                            months={months}
                            active={month}
                            basePath="/ledger"
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
                                            className={`${r.row_type !== "transaction" ? "row--meta" : ""} ${flagClass(r.status_flag)}`}
                                            title={r.status_flag ?? undefined}
                                        >
                                            <td className="num muted">
                                                {r.source_row}
                                            </td>
                                            <td>{r.obr}</td>
                                            <td className="ellip">{r.payee}</td>
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
