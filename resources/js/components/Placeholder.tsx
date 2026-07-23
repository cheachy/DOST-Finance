import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../layouts/AppLayout";
import "../../css/theme.css";
import "../../css/dashboard.css";

interface PlaceholderProps {
    title: string;
    blurb: string;
    current: "ledger" | "subsidiary" | "reports" | "logs";
}

/**
 * Shared "not built yet" page, so the sidebar links resolve while the later
 * phases are still in progress. Replace each usage with the real page.
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
            </header>

            <div className="dash-body">
                <section className="dash-card">
                    <div className="dash-empty">
                        <p>This section isn’t built yet.</p>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
