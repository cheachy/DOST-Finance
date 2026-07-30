import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../layouts/AppLayout";
import MonthSelector from "../../components/MonthSelector";
import LedgerTable from "../../components/LedgerTable";
import { flagClass } from "../../lib/ledger";
import type { LedgerRow, Paginator } from "../../types/ledger";
import "../../../css/theme.css";
import "../../../css/dashboard.css";
import "../../../css/ledger.css";
import LiveMeta from "../../components/LiveMeta";

export default function Index() {
    const page = usePage().props as any;
    const { auth, hasLedger, snapshot, months, month, rows } = page;
    if (!auth?.user) return null;

    const paginator: Paginator<LedgerRow> | null = rows;

    return (
        <AppLayout user={auth.user} current="ledger">
            <Head title="General Ledger" />

            <header className="dash-topbar">
                <div>
                    <h1 className="dash-topbar__title">General Ledger</h1>
                    <p className="dash-topbar__sub">
                        {hasLedger
                            ? `${snapshot.original_name} · ${snapshot.transaction_count.toLocaleString()} transactions`
                            : "No ledger imported yet."}
                    </p>
                </div>
                <div className="dash-topbar__meta">
                    <span>Butuan City, PH</span>
                    <LiveMeta />
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

                        <LedgerTable
                            paginator={paginator}
                            rowClassName={(r) =>
                                `${r.row_type !== "transaction" ? "row--meta" : ""} ${flagClass(r.status_flag)}`.trim()
                            }
                        />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
