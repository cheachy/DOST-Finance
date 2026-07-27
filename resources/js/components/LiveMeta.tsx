import { useState, useEffect } from "react";

export default function LiveMeta() {
    const dateOptions = {
        year: "numeric",
        month: "long",
        day: "numeric",
    } as const;

    const [date] = useState(() =>
        new Date().toLocaleDateString("en-PH", dateOptions),
    );

    const [time, setTime] = useState(new Date().toLocaleTimeString());

    useEffect(() => {
        const timer = setInterval(() => {
            setTime(new Date().toLocaleTimeString());
        }, 1000);

        return () => clearInterval(timer);
    }, []);

    return (
        <>
            <span className="dash-topbar__clock">{time}</span>
            <span>{date}</span>
        </>
    );
}
