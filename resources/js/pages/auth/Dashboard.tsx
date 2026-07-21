import { Head, Link, usePage, router } from "@inertiajs/react";
import { useRef, useState, ChangeEvent } from "react";

export default function Dashboard() {
  const { auth, status, errors } = usePage().props;
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [processing, setProcessing] = useState(false);

  function handleFileChange(e: ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;

    setProcessing(true);
    router.post(
      "/imports",
      { file, year: new Date().getFullYear() },
      {
        forceFormData: true,
        onFinish: () => {
          setProcessing(false);
          e.target.value = ""; // allow re-selecting the same file later
        },
      }
    );
  }

  if (!auth.user) return null;

  return (
    <div className="dashboard-page">
      <Head title="Dashboard" />

      <header className="dashboard-topbar">
        <p className="dashboard-brand">Talaan</p>

        <div className="dashboard-user">
          <span className="dashboard-username">{auth.user.name}</span>
          <Link href="/logout" method="post" as="button" className="dashboard-logout">
            Log out
          </Link>
        </div>
      </header>

      <main className="dashboard-main">
        <div className="dashboard-import-panel">
          <h1>Process your Ledger Spreadsheet</h1>
          <p>Upload your Excel file to instantly edit values, run automatic calculations, and preview live summaries.</p>

          {status && <p className="dashboard-import-status">{status}</p>}
          {(errors?.file || errors?.year) && (
            <p className="dashboard-import-error">{errors.file || errors.year}</p>
          )}

          <div className="dashboard-import-dropzone">
            <button
              type="button"
              className="dashboard-import-button"
              disabled={processing}
              onClick={() => fileInputRef.current?.click()}
            >
              {processing ? "Processing…" : "Select Excel file"}
            </button>
            <input
              ref={fileInputRef}
              type="file"
              accept=".xlsx,.xls"
              onChange={handleFileChange}
              style={{ display: "none" }}
            />
            <p className="dashboard-import-hint">or drop spreadsheet here</p>
          </div>
        </div>
      </main>
    </div>
  );
}
