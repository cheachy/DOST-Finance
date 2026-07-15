import { Head, Link, usePage } from "@inertiajs/react";

export default function Dashboard() {
  const { auth } = usePage().props;

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
        <button type="button" className="dashboard-import-button">
          Import
        </button>
      </main>
    </div>
  );
}