import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../layouts/AppLayout";
import "../../css/theme.css";
import "../../css/dashboard.css";
import LiveMeta from "./LiveMeta";

interface PlaceholderProps {
    title: string;
    blurb: string;
    current: "ledger" | "subsidiary" | "reports" | "logs";
}

/**
 * Not yet built.
 */
export default function Placeholder({
    title,
    blurb,
    current,
}: PlaceholderProps) {
    const { auth } = usePage().props as any;
    if (!auth?.user) return null;

    return (
        <AppLayout user={auth.user} current={current}>
            <Head title={title} />

            <header className="dash-topbar">
                <div>
                    <h1 className="dash-topbar__title">{title}</h1>
                    <p className="dash-topbar__sub">{blurb}</p>
                </div>
                <div className="dash-topbar__meta">
                    <span>Butuan City, PH</span>
                    <LiveMeta />
                </div>
            </header>

            <div className="dash-body">
                <section className="dash-card">
                    <div className="dash-empty">
                        <p>This section isn't built yet.</p>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
