import { Head, usePage, router } from "@inertiajs/react";
import AppLayout from "../../layouts/AppLayout";
import MonthSelector from "../../components/MonthSelector";
import LedgerTable from "../../components/LedgerTable";
import type { LedgerRow, Paginator } from "../../types/ledger";
import "../../../css/theme.css";
import "../../../css/dashboard.css";
import "../../../css/ledger.css";

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

    const paginator: Paginator<LedgerRow> | null = rows;

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

                        <LedgerTable
                            paginator={paginator}
                            emptyMessage={`No ${tab} transactions for this period.`}
                        />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
