import { useState } from "react";
import {
    Bar,
    BarChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    TooltipContentProps,
    XAxis,
    YAxis,
} from "recharts";

interface MonthPoint {
    month: number;
    allotted: number;
    disbursed: number;
}

interface MonthlyTrendChartProps {
    data: MonthPoint[];
}

const NAMES = [
    "Jan", "Feb", "Mar", "Apr", "May", "Jun",
    "Jul", "Aug", "Sep", "Oct", "Nov", "Dec",
];

const CHART_HEIGHT = 230;

const peso = (n: number) =>
    new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" }).format(n || 0);

function pesoCompact(n: number) {
    const abs = Math.abs(n);
    if (abs >= 1_000_000) return `₱${(n / 1_000_000).toFixed(1)}M`;
    if (abs >= 1_000) return `₱${(n / 1_000).toFixed(0)}K`;
    return `₱${n.toFixed(0)}`;
}

const axisTick = { fontSize: 9, fill: "var(--aslr-faint)", fontFamily: "var(--font-sans)" };

function ChartTooltip({ active, payload, label }: TooltipContentProps) {
    if (!active || !payload?.length) return null;
    return (
        <div className="chart-tooltip__content">
            <span className="chart-tooltip__month">{NAMES[(label as number) - 1]}</span>
            {payload.map((p) => (
                <span key={p.dataKey as string}>
                    <span className="chart-tooltip__label">
                        {p.dataKey === "allotted" ? "Allotted" : "Disbursed"}
                    </span>{" "}
                    <span className="chart-tooltip__value">{peso(p.value as number)}</span>
                </span>
            ))}
        </div>
    );
}

export default function MonthlyTrendChart({ data }: MonthlyTrendChartProps) {
    const [showTable, setShowTable] = useState(false);

    return (
        <div>
            <div className="chart-legend">
                <span className="chart-legend__item">
                    <span className="chart-legend__swatch chart-legend__swatch--allotted" />
                    Allotted
                </span>
                <span className="chart-legend__item">
                    <span className="chart-legend__swatch chart-legend__swatch--disbursed" />
                    Disbursed
                </span>
                <button
                    type="button"
                    className="chart-table-toggle"
                    onClick={() => setShowTable((s) => !s)}
                    aria-expanded={showTable}
                >
                    {showTable ? "Show chart" : "View as table"}
                </button>
            </div>

            {showTable ? (
                <table className="chart-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Allotted</th>
                            <th>Disbursed</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.map((d) => (
                            <tr key={d.month}>
                                <td>{NAMES[d.month - 1]}</td>
                                <td>{peso(d.allotted)}</td>
                                <td>{peso(d.disbursed)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            ) : (
                <div className="chart-svg-wrap">
                    <ResponsiveContainer width="100%" height={CHART_HEIGHT}>
                        <BarChart data={data} barGap={2} margin={{ top: 10, right: 8, left: 0, bottom: 0 }}>
                            <CartesianGrid vertical={false} stroke="var(--aslr-border)" />
                            <XAxis
                                dataKey="month"
                                tickFormatter={(m: number) => NAMES[m - 1] ?? ""}
                                tick={axisTick}
                                axisLine={{ stroke: "var(--aslr-border)" }}
                                tickLine={false}
                            />
                            <YAxis
                                tickFormatter={pesoCompact}
                                tick={axisTick}
                                axisLine={false}
                                tickLine={false}
                                width={44}
                            />
                            <Tooltip
                                content={ChartTooltip}
                                cursor={{ fill: "var(--aslr-border)", opacity: 0.4 }}
                            />
                            <Bar
                                dataKey="allotted"
                                fill="var(--chart-allotted)"
                                radius={[4, 4, 0, 0]}
                                maxBarSize={24}
                            />
                            <Bar
                                dataKey="disbursed"
                                fill="var(--chart-disbursed)"
                                radius={[4, 4, 0, 0]}
                                maxBarSize={24}
                            />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            )}
        </div>
    );
}
