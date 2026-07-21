import { Head, Link } from "@inertiajs/react";
import React from "react";

export default function LedgerPreview({ importId, transactions = [] }) {
  const formatCurrency = (val) => {
    if (val === null || val === undefined) return "-";
    const num = Number(val);
    if (isNaN(num)) return "-";
    return num.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  return (
    <div className="ledger-fullscreen">
      <Head title="Ledger Preview" />

      <header className="ledger-fullscreen-header">
        <div className="header-left">
          <Link href="/dashboard" className="back-btn">
            &larr; Back to Dashboard
          </Link>
          <h1>Import Preview (ID: {importId})</h1>
        </div>
        <div className="header-right">
          <span className="record-count">{transactions.length} records</span>
        </div>
      </header>

      <main className="ledger-fullscreen-main">
        {transactions.length > 0 ? (
          <div className="ledger-table-wrapper-full">
            <table className="ledger-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Date</th>
                  <th>Payee</th>
                  <th>Particulars</th>
                  <th>OBR / DV</th>
                  <th className="col-num">Gross</th>
                  <th className="col-num">Deductions</th>
                  <th className="col-num">Net</th>
                </tr>
              </thead>
              <tbody>
                {transactions.map((t) => (
                  <tr>
                    <td className="col-num">{t.id}</td>
                    <td className="col-date">{t.date || "-"}</td>
                    <td>{t.payee?.name || "-"}</td>
                    <td className="col-particulars">{t.particulars || "-"}</td>
                    <td>
                      {t.obr_sequence || "-"} / {t.dv_sequence || "-"}
                    </td>
                    <td className="col-amount">{formatCurrency(t.gross)}</td>
                    <td className="col-amount amount-negative">
                      {t.charging_breakdown ? formatCurrency(t.charging_breakdown) : "-"}
                    </td>
                    <td className="col-amount amount-positive">{formatCurrency(t.net)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="ledger-empty">
            No transactions found for this import.
          </div>
        )}
      </main>
    </div>
  );
}
