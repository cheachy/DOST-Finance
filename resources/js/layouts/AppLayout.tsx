import { Link } from "@inertiajs/react";
import type { ReactNode } from "react";
import DostLogo from "../components/DostLogo";

interface AppLayoutProps {
    user: { name: string; email?: string; role?: string };
    current: "dashboard" | "ledger" | "subsidiary" | "reports" | "logs";
    children: ReactNode;
}

/* Sidebar is organised around NOUNS (places), not verbs (actions).
   Import / generate / export are buttons inside the document they act on. */
const NAV = [
    { key: "dashboard", label: "Dashboard", href: "/dashboard", icon: "grid" },
    { key: "ledger", label: "General ledger", href: "/ledger", icon: "book" },
    {
        key: "subsidiary",
        label: "Subsidiary ledgers",
        href: "/subsidiary-ledgers",
        icon: "layers",
    },
    { key: "reports", label: "Reports", href: "/reports", icon: "report" },
    { key: "logs", label: "Activity log", href: "/logs", icon: "clock" },
] as const;

const stroke = {
    fill: "none",
    stroke: "currentColor",
    strokeWidth: 1.7,
    strokeLinecap: "round" as const,
    strokeLinejoin: "round" as const,
};

function NavIcon({ name }: { name: string }) {
    const paths: Record<string, ReactNode> = {
        grid: (
            <>
                <rect x="3" y="3" width="7" height="7" rx="1.5" />
                <rect x="14" y="3" width="7" height="7" rx="1.5" />
                <rect x="3" y="14" width="7" height="7" rx="1.5" />
                <rect x="14" y="14" width="7" height="7" rx="1.5" />
            </>
        ),
        book: (
            <>
                <path d="M4 4.5A1.5 1.5 0 0 1 5.5 3H19v16H5.5A1.5 1.5 0 0 0 4 20.5Z" />
                <path d="M8 7h7M8 11h7" />
            </>
        ),
        layers: (
            <>
                <path d="m12 3 8 4.5-8 4.5-8-4.5Z" />
                <path d="m4 12 8 4.5 8-4.5" />
                <path d="m4 16.5 8 4.5 8-4.5" />
            </>
        ),
        report: (
            <>
                <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z" />
                <path d="M14 3v5h5M9 13h6M9 17h4" />
            </>
        ),
        clock: (
            <>
                <circle cx="12" cy="12" r="9" />
                <path d="M12 7v5l3 2" />
            </>
        ),
    };
    return (
        <svg
            viewBox="0 0 24 24"
            width="17"
            height="17"
            {...stroke}
            aria-hidden="true"
        >
            {paths[name]}
        </svg>
    );
}

export default function AppLayout({ user, current, children }: AppLayoutProps) {
    const initial = user.name?.trim().charAt(0).toUpperCase() || "?";

    return (
        <div className="app-shell">
            <aside className="app-sidebar">
                <div className="app-sidebar__brand">
                    <span className="app-sidebar__mark">
                        <DostLogo size={22} showText={false} />
                    </span>
                    <span className="app-sidebar__brand-text">
                        <span className="app-sidebar__brand-org">
                            DOST CARAGA
                        </span>
                        <span className="app-sidebar__brand-app">
                            Finance Admin
                        </span>
                    </span>
                </div>

                <div className="app-sidebar__user">
                    <span className="app-sidebar__avatar">{initial}</span>
                    <span className="app-sidebar__user-text">
                        <span className="app-sidebar__user-name">
                            {user.name}
                        </span>
                        <span className="app-sidebar__user-role">
                            {user.role ?? "FAD-Accounting"}
                        </span>
                    </span>
                </div>

                <nav className="app-sidebar__nav">
                    {NAV.map((item) => (
                        <Link
                            key={item.key}
                            href={item.href}
                            className={`app-nav__item${current === item.key ? " is-active" : ""}`}
                        >
                            <NavIcon name={item.icon} />
                            <span>{item.label}</span>
                        </Link>
                    ))}
                </nav>

                <div className="app-sidebar__foot">
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        className="app-sidebar__logout"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            width="16"
                            height="16"
                            {...stroke}
                            aria-hidden="true"
                        >
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                            <path d="m16 17 5-5-5-5M21 12H9" />
                        </svg>
                        <span>Log out</span>
                    </Link>
                    <p className="app-sidebar__copy">
                        DOST-CARAGA SYSTEM © {new Date().getFullYear()}
                    </p>
                </div>
            </aside>

            <div className="app-main">{children}</div>
        </div>
    );
}
