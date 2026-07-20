import { useRef } from "react";
import { Head, Link, usePage } from "@inertiajs/react";

export default function Dashboard() {
  const { auth } = usePage().props;
  const fileInputRef = useRef(null);

  // Select file 
  const triggerFileSelect = () => {
    fileInputRef.current?.click();
  };

  const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (file) {
      console.log("File selected:", file.name);
      // PHPSpreadsheet and TabularJS codes up here
    }
  };

  return (
    <div className="dashboard-page">
      <Head title="Dashboard" />

      {/* Navigation Bar */}
      <header className="dashboard-topbar">
        <p className="dashboard-brand">Talaan</p>

        <div className="dashboard-user">
          <span className="dashboard-username">{auth.user.name}</span>
          <span className="dashboard-user-divider">|</span>
          <Link href="/logout" method="post" as="button" className="dashboard-logout">
            Log out
          </Link>
        </div>
      </header>

      {/* Hero Section */}
      <main className="dashboard-hero-container">
        <div className="dashboard-hero-content">
          <h1 className="dashboard-hero-title">Process your Ledger Spreadsheet</h1>
          <p className="dashboard-hero-subtitle">
            Upload your Excel file to instantly edit values, run automatic calculations, and preview live summaries.
          </p>

          {/* Dropzone Wrapper */}
          <div className="dashboard-dropzone">
            {/* File input */}
            <input 
              type="file" 
              ref={fileInputRef} 
              onChange={handleFileChange} 
              accept=".xlsx, .xls, .csv" 
              className="hidden-file-input"
            />

            {/* Action Button */}
            <button 
              type="button" 
              onClick={triggerFileSelect} 
              className="dashboard-import-button-large"
            >
              Select Excel file
            </button>

            <span className="dashboard-dropzone-text">or drop spreadsheet here</span>
          </div>
        </div>
      </main>

      {/* Footer */}
      <footer className="dashboard-footer">
        <p>© 2026 Talaan Ledger Manager. Built for clean accounting.</p>
      </footer>
    </div>
  );
}