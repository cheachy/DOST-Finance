import { Head, router, usePage } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";
import AppLayout from "../../layouts/AppLayout";
import LiveMeta from "../../components/LiveMeta";
import "../../../css/theme.css";
import "../../../css/dashboard.css";

interface Snapshot {
    id: number;
    original_name: string;
    fiscal_year: number;
    transaction_count: number;
    has_file: boolean;
    rod_export_status: "queued" | "done" | "failed" | null;
    rod_export_error: string | null;
    rod_exported_at: string | null;
    rod_export_ready: boolean;
    /** Finished once, but its .xlsx is no longer on disk. */
    rod_export_missing: boolean;
}

const POLL_MS = 5000;

export default function Index() {
    const page = usePage().props as any;
    const { auth, hasLedger, snapshot, errors } = page as {
        auth: any;
        hasLedger: boolean;
        snapshot: Snapshot | null;
        errors?: Record<string, string>;
    };

    const [queuing, setQueuing] = useState(false);
    const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

    // While an export is queued, poll for its finished status instead of
    // making her sit on a spinner — the write-back itself runs on the queue
    // (RodExportJob) because it takes minutes against the real workbook, not
    // seconds, so this page has to check back rather than hold a request open.
    useEffect(() => {
        if (snapshot?.rod_export_status === "queued") {
            pollRef.current = setInterval(() => {
                router.reload({ only: ["snapshot"] });
            }, POLL_MS);
        }
        return () => {
            if (pollRef.current) clearInterval(pollRef.current);
        };
    }, [snapshot?.rod_export_status]);

    if (!auth?.user) return null;

    function queueExport() {
        setQueuing(true);
        router.post(
            "/reports/rod-export",
            {},
            { onFinish: () => setQueuing(false) },
        );
    }

    const status = snapshot?.rod_export_status ?? null;

    return (
        <AppLayout user={auth.user} current="reports">
            <Head title="Reports" />

            <header className="dash-topbar">
                <div>
                    <h1 className="dash-topbar__title">Reports</h1>
                    <p className="dash-topbar__sub">
                        Monthly reports of disbursement and summaries.
                    </p>
                </div>
                <div className="dash-topbar__meta">
                    <span>Butuan City, PH</span>
                    <LiveMeta />
                </div>
            </header>

            <div className="dash-body">
                <section className="dash-card">
                    {!hasLedger || !snapshot ? (
                        <div className="dash-empty">
                            <p>
                                Import a workbook from the dashboard before
                                generating a report.
                            </p>
                        </div>
                    ) : (
                        <>
                            <h2 className="reports-title">
                                ROD export — testing
                            </h2>
                            <p className="reports-desc">
                                Writes the generated current-year PS/MOOE/CO
                                figures into a copy of{" "}
                                <strong>{snapshot.original_name}</strong> (
                                {snapshot.transaction_count.toLocaleString()}{" "}
                                transactions, FY{snapshot.fiscal_year}). The
                                stored snapshot itself is never modified —
                                prior-year columns are left untouched, and
                                only A/C-gated current-year transaction rows
                                receive a value.
                            </p>
                            <p className="reports-subtext">
                                Runs in the background — the real workbook is
                                large enough that writing it back out takes a
                                few minutes. This page checks back
                                automatically; no need to keep it open.
                            </p>

                            {!snapshot.has_file ? (
                                <p className="reports-error">
                                    The original workbook file for this
                                    snapshot is no longer on disk, so it can't
                                    be used as an export template.
                                </p>
                            ) : status === "queued" ? (
                                <p className="reports-success">
                                    Generating — this page will update
                                    automatically once it's ready.
                                </p>
                            ) : (
                                <div className="reports-actions">
                                    <button
                                        type="button"
                                        className="dash-btn dash-btn--primary"
                                        disabled={queuing}
                                        onClick={queueExport}
                                    >
                                        {status === "done" || status === "failed"
                                            ? "Re-run export"
                                            : "Export ROD (testing)"}
                                    </button>

                                    {status === "done" &&
                                        snapshot.rod_export_ready && (
                                            <a
                                                href="/reports/rod-export/download"
                                                className="dash-btn"
                                            >
                                                Download last export
                                                {snapshot.rod_exported_at
                                                    ? ` (${snapshot.rod_exported_at})`
                                                    : ""}
                                            </a>
                                        )}
                                </div>
                            )}

                            {errors?.rod_export && (
                                <p className="reports-error reports-error--margin">
                                    {errors.rod_export}
                                </p>
                            )}

                            {status === "done" &&
                                snapshot.rod_export_missing &&
                                !errors?.rod_export && (
                                    <p className="reports-missing">
                                        The last export
                                        {snapshot.rod_exported_at
                                            ? ` (${snapshot.rod_exported_at})`
                                            : ""}{" "}
                                        is no longer on disk, so there's
                                        nothing to download. Re-run it to
                                        generate a fresh copy.
                                    </p>
                                )}

                            {status === "failed" && (
                                <p className="reports-error reports-error--margin">
                                    Last attempt failed:{" "}
                                    {snapshot.rod_export_error ??
                                        "unknown error"}
                                </p>
                            )}
                        </>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
