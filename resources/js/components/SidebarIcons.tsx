import type { ReactNode } from "react";

export const stroke = {
    fill: "none",
    stroke: "currentColor",
    strokeWidth: 1.7,
    strokeLinecap: "round" as const,
    strokeLinejoin: "round" as const,
};

const NAV_ICON_PATHS: Record<string, ReactNode> = {
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

export function NavIcon({ name }: { name: string }) {
    return (
        <svg
            viewBox="0 0 24 24"
            width="17"
            height="17"
            {...stroke}
            aria-hidden="true"
        >
            {NAV_ICON_PATHS[name]}
        </svg>
    );
}

export function ChevronLeftIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            width="13"
            height="13"
            {...stroke}
            aria-hidden="true"
        >
            <path d="m14 6-6 6 6 6" />
        </svg>
    );
}

export function LogoutIcon() {
    return (
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
    );
}
