import { Head, Link } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import finance from '@/routes/finance';

type ReportRow = {
    project: string;
    goal: string;
    action: string;
    cog_code: string;
    cog_name: string;
    amount_cents: number;
    month: string;
};

type ConcentratedRow = {
    cog_code: string;
    cog_name: string;
    amount_cents: number;
    committed_cents: number;
    executed_cents: number;
    available_cents: number;
};

type BudgetCubeRow = {
    cog_code: string;
    cog_name: string;
    months: Record<string, number>;
    total_cents: number;
};

type DashboardAmount = {
    label: string;
    amount_cents: number;
};

type DashboardPartidaMonth = {
    label: string;
    months: Record<string, number>;
    total_cents: number;
};

type Props = {
    program: {
        id: number;
        fiscal_year: number;
        name: string;
    };
    reports: {
        months: string[];
        desglose: ReportRow[];
        concentrado: ConcentratedRow[];
        presupuesto: BudgetCubeRow[];
        presupuesto_totals: {
            months: Record<string, number>;
            total_cents: number;
        };
        dashboard: {
            by_action: DashboardAmount[];
            by_partida: DashboardAmount[];
            by_chapter: DashboardAmount[];
            partida_by_month: DashboardPartidaMonth[];
        };
    };
};

type ReportTab = 'dashboard' | 'desglose' | 'concentrado' | 'presupuesto';

const chartColors = ['#047857', '#2563eb', '#c2410c', '#7c3aed', '#0f766e'];

function money(cents: number): string {
    return (cents / 100).toLocaleString('es-MX', {
        style: 'currency',
        currency: 'MXN',
    });
}

function percent(amount: number, total: number): number {
    return total > 0 ? Math.round((amount / total) * 1000) / 10 : 0;
}

function maxAmount(rows: DashboardAmount[]): number {
    return Math.max(...rows.map((row) => row.amount_cents), 0);
}

function HorizontalBars({
    rows,
    title,
}: {
    rows: DashboardAmount[];
    title: string;
}) {
    const total = rows.reduce((sum, row) => sum + row.amount_cents, 0);
    const max = maxAmount(rows);

    return (
        <section className="min-w-0 overflow-hidden rounded-lg border">
            <div className="border-b bg-muted/50 px-4 py-3">
                <h2 className="text-sm font-semibold">{title}</h2>
            </div>
            <div className="grid min-w-0 gap-3 p-4">
                {rows.map((row, index) => (
                    <div className="grid min-w-0 gap-1.5" key={row.label}>
                        <div className="flex min-w-0 gap-3 text-sm">
                            <span className="min-w-0 flex-1 truncate font-medium">
                                {row.label}
                            </span>
                            <span className="shrink-0 text-right font-semibold">
                                {money(row.amount_cents)}
                            </span>
                        </div>
                        <div className="min-w-0 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-3 rounded-full"
                                style={{
                                    width: `${percent(row.amount_cents, max)}%`,
                                    backgroundColor:
                                        chartColors[index % chartColors.length],
                                }}
                            />
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {percent(row.amount_cents, total)}% del total
                            asignado
                        </p>
                    </div>
                ))}
            </div>
        </section>
    );
}

function ChapterPie({ rows }: { rows: DashboardAmount[] }) {
    const total = rows.reduce((sum, row) => sum + row.amount_cents, 0);
    let cursor = 0;
    const segments = rows.map((row, index) => {
        const start = cursor;
        const share = total > 0 ? (row.amount_cents / total) * 100 : 0;
        cursor += share;

        return `${chartColors[index % chartColors.length]} ${start}% ${cursor}%`;
    });

    return (
        <section className="overflow-hidden rounded-lg border">
            <div className="border-b bg-muted/50 px-4 py-3">
                <h2 className="text-sm font-semibold">
                    Presupuesto por capítulo
                </h2>
            </div>
            <div className="grid gap-5 p-4 md:grid-cols-[220px_1fr] md:items-center">
                <div
                    aria-label="Distribución por capítulo"
                    className="mx-auto aspect-square w-full max-w-56 rounded-full border"
                    role="img"
                    style={{
                        background:
                            total > 0
                                ? `conic-gradient(${segments.join(', ')})`
                                : '#e5e7eb',
                    }}
                />
                <div className="grid gap-3">
                    {rows.map((row, index) => (
                        <div className="flex gap-3 text-sm" key={row.label}>
                            <span
                                className="mt-1 size-3 shrink-0 rounded-sm"
                                style={{
                                    backgroundColor:
                                        chartColors[index % chartColors.length],
                                }}
                            />
                            <div className="min-w-0 flex-1">
                                <p className="font-medium">{row.label}</p>
                                <p className="text-muted-foreground">
                                    {money(row.amount_cents)} ·{' '}
                                    {percent(row.amount_cents, total)}%
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

function PartidaMonthMatrix({
    months,
    rows,
}: {
    months: string[];
    rows: DashboardPartidaMonth[];
}) {
    const max = Math.max(
        ...rows.flatMap((row) => months.map((month) => row.months[month] ?? 0)),
        0,
    );

    return (
        <section className="overflow-hidden rounded-lg border">
            <div className="border-b bg-muted/50 px-4 py-3">
                <h2 className="text-sm font-semibold">
                    Presupuesto por partida y mes
                </h2>
            </div>
            <div className="overflow-x-auto">
                <table className="min-w-[1040px] text-sm">
                    <thead className="bg-muted/60 text-left">
                        <tr>
                            <th className="px-3 py-2">Partida</th>
                            {months.map((month) => (
                                <th className="px-3 py-2" key={month}>
                                    {month}
                                </th>
                            ))}
                            <th className="px-3 py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr className="border-t align-top" key={row.label}>
                                <td className="max-w-80 px-3 py-2 font-medium">
                                    {row.label}
                                </td>
                                {months.map((month) => {
                                    const amount = row.months[month] ?? 0;

                                    return (
                                        <td
                                            className="min-w-36 px-3 py-2"
                                            key={month}
                                        >
                                            <div className="grid gap-1">
                                                <div className="h-2 overflow-hidden rounded-full bg-muted">
                                                    <div
                                                        className="h-full rounded-full bg-emerald-700"
                                                        style={{
                                                            width: `${percent(amount, max)}%`,
                                                        }}
                                                    />
                                                </div>
                                                <span className="text-xs text-muted-foreground">
                                                    {money(amount)}
                                                </span>
                                            </div>
                                        </td>
                                    );
                                })}
                                <td className="px-3 py-2 text-right font-semibold">
                                    {money(row.total_cents)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

export default function U300FinancialReports({ program, reports }: Props) {
    const [activeReport, setActiveReport] = useState<ReportTab>('dashboard');

    return (
        <>
            <Head title="Reportes financieros U300" />
            <main className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <header className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Presupuesto U300 · {program.fiscal_year}
                        </p>
                        <h1 className="text-xl leading-7 font-semibold">
                            Reportes financieros
                        </h1>
                        <p className="mt-1 max-w-4xl text-sm text-muted-foreground">
                            {program.name}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href={finance.u300.programs.show(program)}>
                                Volver
                            </Link>
                        </Button>
                        <Button asChild>
                            <a
                                href={
                                    finance.u300.programs.financialReports.export(
                                        program,
                                    ).url
                                }
                            >
                                <Download className="size-4" />
                                Exportar Excel
                            </a>
                        </Button>
                    </div>
                </header>

                <div className="overflow-x-auto">
                    <ToggleGroup
                        className="w-max"
                        onValueChange={(value) => {
                            if (value) {
                                setActiveReport(value as ReportTab);
                            }
                        }}
                        type="single"
                        value={activeReport}
                        variant="outline"
                    >
                        <ToggleGroupItem className="px-4" value="dashboard">
                            DASHBOARD
                        </ToggleGroupItem>
                        <ToggleGroupItem className="px-4" value="desglose">
                            DESGLOSE
                        </ToggleGroupItem>
                        <ToggleGroupItem className="px-4" value="concentrado">
                            CONCENTRADO
                        </ToggleGroupItem>
                        <ToggleGroupItem className="px-4" value="presupuesto">
                            PRESUPUESTO
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>

                {activeReport === 'dashboard' && (
                    <div className="grid min-w-0 gap-4">
                        <div className="grid min-w-0 gap-4 xl:grid-cols-2">
                            <HorizontalBars
                                rows={reports.dashboard.by_action}
                                title="Presupuesto por acción"
                            />
                            <HorizontalBars
                                rows={reports.dashboard.by_partida}
                                title="Presupuesto por partida"
                            />
                        </div>
                        <ChapterPie rows={reports.dashboard.by_chapter} />
                        <PartidaMonthMatrix
                            months={reports.months}
                            rows={reports.dashboard.partida_by_month}
                        />
                    </div>
                )}

                {activeReport === 'desglose' && (
                    <section className="overflow-hidden rounded-lg border">
                        <div className="border-b bg-muted/50 px-4 py-3">
                            <h2 className="text-sm font-semibold">DESGLOSE</h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-[1120px] text-sm">
                                <thead className="bg-muted/60 text-left">
                                    <tr>
                                        <th className="px-3 py-2">Proyecto</th>
                                        <th className="px-3 py-2">Meta</th>
                                        <th className="px-3 py-2">Acción</th>
                                        <th className="px-3 py-2">COG</th>
                                        <th className="px-3 py-2">Partida</th>
                                        <th className="px-3 py-2 text-right">
                                            Monto
                                        </th>
                                        <th className="px-3 py-2">Mes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {reports.desglose.map((row, index) => (
                                        <tr
                                            className="border-t align-top"
                                            key={index}
                                        >
                                            <td className="max-w-72 px-3 py-2">
                                                {row.project}
                                            </td>
                                            <td className="max-w-72 px-3 py-2">
                                                {row.goal}
                                            </td>
                                            <td className="max-w-72 px-3 py-2">
                                                {row.action}
                                            </td>
                                            <td className="px-3 py-2 font-semibold">
                                                {row.cog_code}
                                            </td>
                                            <td className="px-3 py-2">
                                                {row.cog_name}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                {money(row.amount_cents)}
                                            </td>
                                            <td className="px-3 py-2 font-semibold">
                                                {row.month}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}

                {activeReport === 'concentrado' && (
                    <section className="overflow-hidden rounded-lg border">
                        <div className="border-b bg-muted/50 px-4 py-3">
                            <h2 className="text-sm font-semibold">
                                CONCENTRADO
                            </h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-[920px] text-sm">
                                <thead className="bg-muted/60 text-left">
                                    <tr>
                                        <th className="px-3 py-2">
                                            Descripción de la partida
                                        </th>
                                        <th className="px-3 py-2">Partida</th>
                                        <th className="px-3 py-2 text-right">
                                            Monto
                                        </th>
                                        <th className="px-3 py-2 text-right">
                                            Comprometido
                                        </th>
                                        <th className="px-3 py-2 text-right">
                                            Ejercido
                                        </th>
                                        <th className="px-3 py-2 text-right">
                                            Disponible
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {reports.concentrado.map((row) => (
                                        <tr
                                            className="border-t"
                                            key={row.cog_code}
                                        >
                                            <td className="px-3 py-2">
                                                {row.cog_name}
                                            </td>
                                            <td className="px-3 py-2 font-semibold">
                                                {row.cog_code}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                {money(row.amount_cents)}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                {money(row.committed_cents)}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                {money(row.executed_cents)}
                                            </td>
                                            <td className="px-3 py-2 text-right font-semibold">
                                                {money(row.available_cents)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}

                {activeReport === 'presupuesto' && (
                    <section className="overflow-hidden rounded-lg border">
                        <div className="border-b bg-muted/50 px-4 py-3">
                            <h2 className="text-sm font-semibold">
                                PRESUPUESTO
                            </h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-[1040px] text-sm">
                                <thead className="bg-muted/60 text-left">
                                    <tr>
                                        <th className="px-3 py-2">
                                            Descripción de la partida
                                        </th>
                                        <th className="px-3 py-2">Partida</th>
                                        {reports.months.map((month) => (
                                            <th
                                                className="px-3 py-2 text-right"
                                                key={month}
                                            >
                                                {month}
                                            </th>
                                        ))}
                                        <th className="px-3 py-2 text-right">
                                            Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {reports.presupuesto.map((row) => (
                                        <tr
                                            className="border-t"
                                            key={row.cog_code}
                                        >
                                            <td className="px-3 py-2">
                                                {row.cog_name}
                                            </td>
                                            <td className="px-3 py-2 font-semibold">
                                                {row.cog_code}
                                            </td>
                                            {reports.months.map((month) => (
                                                <td
                                                    className="px-3 py-2 text-right"
                                                    key={month}
                                                >
                                                    {money(
                                                        row.months[month] ?? 0,
                                                    )}
                                                </td>
                                            ))}
                                            <td className="px-3 py-2 text-right font-semibold">
                                                {money(row.total_cents)}
                                            </td>
                                        </tr>
                                    ))}
                                    <tr className="border-t bg-muted/50 font-semibold">
                                        <td className="px-3 py-2">Total</td>
                                        <td className="px-3 py-2" />
                                        {reports.months.map((month) => (
                                            <td
                                                className="px-3 py-2 text-right"
                                                key={month}
                                            >
                                                {money(
                                                    reports.presupuesto_totals
                                                        .months[month] ?? 0,
                                                )}
                                            </td>
                                        ))}
                                        <td className="px-3 py-2 text-right">
                                            {money(
                                                reports.presupuesto_totals
                                                    .total_cents,
                                            )}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}
            </main>
        </>
    );
}
