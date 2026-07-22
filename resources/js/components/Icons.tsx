const stroke = {
    fill: "none",
    stroke: "currentColor",
    strokeWidth: 2,
    strokeLinecap: "round" as const,
    strokeLinejoin: "round" as const,
};

export const IUser = () => (
    <svg viewBox="0 0 24 24" width="16" height="16" {...stroke}>
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
        <circle cx="12" cy="7" r="4" />
    </svg>
);

export const ILock = () => (
    <svg viewBox="0 0 24 24" width="16" height="16" {...stroke}>
        <rect x="3" y="11" width="18" height="11" rx="2" />
        <path d="M7 11V7a5 5 0 0 1 10 0v4" />
    </svg>
);

export const IEye = () => (
    <svg viewBox="0 0 24 24" width="16" height="16" {...stroke}>
        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
        <circle cx="12" cy="12" r="3" />
    </svg>
);

export const IEyeOff = () => (
    <svg viewBox="0 0 24 24" width="16" height="16" {...stroke}>
        <path d="M9.9 4.24A9.1 9.1 0 0 1 12 4c6.5 0 10 7 10 7a13 13 0 0 1-2.16 2.92" />
        <path d="M6.06 6.06A13 13 0 0 0 2 11s3.5 7 10 7a9 9 0 0 0 4.94-1.06" />
        <path d="m2 2 20 20" />
    </svg>
);

export const IArrow = () => (
    <svg viewBox="0 0 24 24" width="16" height="16" {...stroke}>
        <path d="M5 12h14" />
        <path d="m12 5 7 7-7 7" />
    </svg>
);

export const IInfo = () => (
    <svg viewBox="0 0 24 24" width="16" height="16" {...stroke}>
        <circle cx="12" cy="12" r="10" />
        <path d="M12 16v-4" />
        <path d="M12 8h.01" />
    </svg>
);

export const ISpinner = () => (
    <svg
        className="aslr-spin"
        viewBox="0 0 24 24"
        width="16"
        height="16"
        fill="none"
    >
        <circle
            cx="12"
            cy="12"
            r="10"
            stroke="currentColor"
            strokeWidth="4"
            opacity="0.25"
        />
        <path
            fill="currentColor"
            opacity="0.75"
            d="M4 12a8 8 0 0 1 8-8V0C5.4 0 0 5.4 0 12h4Z"
        />
    </svg>
);
