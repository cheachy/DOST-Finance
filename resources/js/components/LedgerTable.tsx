import { Link } from "@inertiajs/react";
import { flagClass, peso } from "../lib/ledger";
import type { LedgerRow, Paginator } from "../types/ledger";

interface LedgerTableProps {
    paginator: Paginator<LedgerRow> | null;
    rowClassName?: (row: LedgerRow) => string;
    emptyMessage?: string;
}

export default function LedgerTable({
    paginator,
    rowClassName = (r) => flagClass(r.status_flag),
    emptyMessage,
}: LedgerTableProps) {
    return (
        <>
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
                                className={rowClassName(r)}
                                title={r.status_flag ?? undefined}
                            >
                                <td className="num muted">{r.source_row}</td>
                                <td>{r.obr}</td>
                                <td className="ellip">{r.payee}</td>
                                <td>{r.charging}</td>
                                <td>{r.rc}</td>
                                <td className="ellip">{r.particulars}</td>
                                <td>{r.status}</td>
                                <td className="num">{peso(r.gross)}</td>
                                <td className="num">{peso(r.net)}</td>
                                <td className="ellip">{r.dv}</td>
                                <td className="center">{r.payment_mode}</td>
                                <td className="muted">{r.pay_date}</td>
                            </tr>
                        ))}
                        {emptyMessage && paginator?.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={12}
                                    className="muted"
                                    style={{
                                        textAlign: "center",
                                        padding: "24px",
                                    }}
                                >
                                    {emptyMessage}
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
    );
}
