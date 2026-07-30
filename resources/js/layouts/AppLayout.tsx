import { Link } from "@inertiajs/react";
import { useEffect, useState, type ReactNode } from "react";
import DostLogo from "../components/DostLogo";
import { ChevronLeftIcon, LogoutIcon, NavIcon } from "../components/SidebarIcons";

const SIDEBAR_COLLAPSED_KEY = "app-sidebar-collapsed";

interface AppLayoutProps {
    user: { name: string; email?: string; role?: string };
    current: "dashboard" | "ledger" | "subsidiary" | "reports" | "logs";
    children: ReactNode;
}

/* Sidebars */
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

export default function AppLayout({ user, current, children }: AppLayoutProps) {
    const initial = user.name?.trim().charAt(0).toUpperCase() || "?";
    const [collapsed, setCollapsed] = useState(
        () => localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === "1",
    );

    useEffect(() => {
        localStorage.setItem(SIDEBAR_COLLAPSED_KEY, collapsed ? "1" : "0");
    }, [collapsed]);

    return (
        <div className="app-shell">
            <aside
                className={`app-sidebar${collapsed ? " is-collapsed" : ""}`}
            >
                <button
                    type="button"
                    className="app-sidebar__toggle"
                    onClick={() => setCollapsed((c) => !c)}
                    aria-label={
                        collapsed ? "Expand sidebar" : "Collapse sidebar"
                    }
                    title={collapsed ? "Expand sidebar" : "Collapse sidebar"}
                >
                    <ChevronLeftIcon />
                </button>

                <div className="app-sidebar__brand">
                    <span className="app-sidebar__mark">
                        <DostLogo size={22} showText={false} />
                    </span>
                    <span className="app-sidebar__brand-text">
                        <span className="app-sidebar__brand-org">
                            DOST CARAGA
                        </span>
                        <span className="app-sidebar__brand-app">FAS</span>
                    </span>
                </div>

                <div className="app-sidebar__user">
                    <span className="app-sidebar__avatar">{initial}</span>
                    <span className="app-sidebar__user-text">
                        <span className="app-sidebar__user-name">
                            {user.name}
                        </span>
                        <span className="app-sidebar__user-role">
                            {user.role}
                        </span>
                    </span>
                </div>

                <nav className="app-sidebar__nav">
                    {NAV.map((item) => (
                        <Link
                            key={item.key}
                            href={item.href}
                            className={`app-nav__item${current === item.key ? " is-active" : ""}`}
                            title={item.label}
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
                        title="Log out"
                    >
                        <LogoutIcon />
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
