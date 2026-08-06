import { Head, usePage } from "@inertiajs/react";
import AppLayout from "../../layouts/AppLayout";
import LiveMeta from "../../components/LiveMeta";
import "../../../css/theme.css";
import "../../../css/dashboard.css";

interface ActivityEvent {
    id: number;
    type: string;
    level: "success" | "info" | "error";
    title: string;
    description: string | null;
    date: string;
    time: string;
    at: string;
}

export default function Index() {
    const page = usePage().props as any;
    const { auth } = page;
    const events: ActivityEvent[] = page.events ?? [];

    const groupedEvents = events.reduce((acc, event) => {
        const date = event.date || "Unknown Date";
        if (!acc[date]) acc[date] = [];
        acc[date].push(event);
        return acc;
    }, {} as Record<string, ActivityEvent[]>);

    if (!auth?.user) return null;

    return (
        <AppLayout user={auth.user} current="logs">
            <Head title="Activity log" />

            <header className="dash-topbar">
                <div>
                    <h1 className="dash-topbar__title">Activity log</h1>
                    <p className="dash-topbar__sub">
                        Imports, sign-ins, and exports — a permanent record,
                        not something anyone edits.
                    </p>
                </div>
                <div className="dash-topbar__meta">
                    <span>Butuan City, PH</span>
                    <LiveMeta />
                </div>
            </header>

            <div className="dash-body">
                <section className="dash-card">
                    <div className="dash-card__head">
                        <h2>
                            {events.length} event
                            {events.length === 1 ? "" : "s"}
                        </h2>
                    </div>

                    {events.length > 0 ? (
                        <div className="dash-activity-groups">
                            {Object.entries(groupedEvents).map(([date, dateEvents]) => (
                                <div key={date} className="dash-activity-group">
                                    <h3 className="dash-activity-date">{date}</h3>
                                    <hr className="dash-activity-divider" />
                                    <ul className="dash-activity">
                                        {dateEvents.map((e) => (
                                            <li key={e.id}>
                                                <span
                                                    className={`dash-activity__dot dash-activity__dot--${e.level}`}
                                                    aria-hidden="true"
                                                />
                                                <span>
                                                    <span className="dash-activity__title">
                                                        {e.title}
                                                    </span>
                                                    {e.description && (
                                                        <span className="dash-activity__detail">
                                                            {e.description}
                                                        </span>
                                                    )}
                                                    <span className="dash-activity__at">
                                                        {e.time}
                                                    </span>
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="dash-empty">
                            <p>No activity yet.</p>
                        </div>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
