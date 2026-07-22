interface DostLogoProps {
    className?: string;
    size?: number;
    showText?: boolean;
}

export default function DostLogo({
    className = "",
    size = 120,
}: DostLogoProps) {
    return (
        <img
            src={`/images/dost-logo.png?v=${Date.now()}`}
            alt="DOST Caraga"
            width={size}
            height={size}
            style={{ objectFit: "contain", display: "block" }}
            className={className}
        />
    );
}
