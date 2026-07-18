import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRightLeft,
    BarChart3,
    ClipboardList,
    Fuel,
    WalletCards,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import budgets from '@/routes/finance/own-revenue/budgets';
import reports from '@/routes/finance/own-revenue/budgets/reports';

type AmountSummary = {
    initial_amount_cents: string;
    modified_amount_cents: string;
    reserved_amount_cents: string;
    committed_amount_cents: string;
    paid_amount_cents: string;
    available_amount_cents: string;
};

type ReportLine = AmountSummary & {
    id: number;
    chapter_code: string;
    chapter_name: string;
    specific_item_code: string;
    specific_item_name: string;
    month: number;
};

type Props = {
    budget: {
        id: number;
        fiscal_year: number;
        status: string;
        region_code: string;
        region_name: string;
    };
    has_initial_budget: boolean;
    filters: {
        applied: {
            chapter_code: string | null;
            specific_item_code: string | null;
            month: number | null;
        };
        options: {
            chapters: Array<{ code: string; name: string }>;
            items: Array<{ code: string; name: string; chapter_code: string }>;
            months: Array<{ value: number }>;
        };
    };
    summary: AmountSummary;
    lines: ReportLine[];
    planning_vs_execution: {
        planned_amount_cents: string;
        paid_amount_cents: string;
        difference_amount_cents: string;
        execution_percentage: string | null;
    };
    modifications: {
        total: number;
        transfer_amount_cents: string;
        rescheduling_amount_cents: string;
        items: Array<{
            id: number;
            type: 'transfer' | 'rescheduling';
            amount_cents: string;
            reason: string;
            source: {
                specific_item_code: string;
                specific_item_name: string;
                month: number;
            };
            destination: {
                specific_item_code: string;
                specific_item_name: string;
                month: number;
            };
            recorded_by_name: string;
            recorded_at: string | null;
        }>;
    };
    expense_dossiers: {
        total: number;
        by_status: Record<string, number>;
        pending_requirements: number;
    };
    fuel: {
        acquired_amount_cents: string;
        confirmed_consumption_cents: string;
        pending_needs_cents: string;
        available_amount_cents: string;
    };
};

const monthNames = [
    '',
    'Enero',
    'Febrero',
    'Marzo',
    'Abril',
    'Mayo',
    'Junio',
    'Julio',
    'Agosto',
    'Septiembre',
    'Octubre',
    'Noviembre',
    'Diciembre',
];

const dossierLabels: Record<string, string> = {
    draft: 'Borrador',
    sufficiency_requested: 'Suficiencia solicitada',
    sufficiency_confirmed: 'Suficiencia confirmada',
    purchase_in_progress: 'Compra en proceso',
    payment_requested: 'Pago solicitado',
    finance_authorized: 'Autorizado por Finanzas',
    budget_office_authorized: 'Autorizado por Presupuesto',
    paid: 'Pagado',
    rejected: 'Rechazado',
    cancelled: 'Cancelado',
};

const selectClassName =
    'h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

function formatCents(value: string): string {
    const cents = BigInt(value || '0');
    const sign = cents < 0n ? '-' : '';
    const absolute = cents < 0n ? -cents : cents;
    const pesos = absolute / 100n;
    const fraction = (absolute % 100n).toString().padStart(2, '0');

    return `${sign}$${pesos.toLocaleString('es-MX')}.${fraction}`;
}

export default function OwnRevenueInternalReportShow(props: Props) {
    const { budget, filters, summary } = props;
    const filteredItems = filters.options.items.filter(
        (item) =>
            filters.applied.chapter_code === null ||
            item.chapter_code === filters.applied.chapter_code,
    );

    const applyFilter = (
        key: 'chapter_code' | 'specific_item_code' | 'month',
        value: string,
    ): void => {
        const nextFilters: Record<string, string | number> = {};
        const next = { ...filters.applied, [key]: value === '' ? null : value };

        if (key === 'chapter_code') {
            const selectedItem = filters.options.items.find(
                (item) => item.code === next.specific_item_code,
            );

            if (
                selectedItem !== undefined &&
                selectedItem.chapter_code !== value
            ) {
                next.specific_item_code = null;
            }
        }

        for (const [filterKey, filterValue] of Object.entries(next)) {
            if (filterValue !== null && filterValue !== '') {
                nextFilters[filterKey] =
                    filterKey === 'month' ? Number(filterValue) : filterValue;
            }
        }

        router.get(reports.show.url(budget.id), nextFilters, {
            preserveState: true,
            replace: true,
        });
    };

    const cards = [
        ['Inicial', summary.initial_amount_cents],
        ['Modificado', summary.modified_amount_cents],
        ['Reservado', summary.reserved_amount_cents],
        ['Comprometido', summary.committed_amount_cents],
        ['Pagado', summary.paid_amount_cents],
        ['Disponible', summary.available_amount_cents],
    ] as const;

    return (
        <>
            <Head
                title={`Reportes de Ingresos Propios ${budget.fiscal_year}`}
            />
            <main className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="grid gap-3">
                    <Button asChild variant="ghost" size="sm" className="w-fit">
                        <Link href={budgets.show(budget.id)}>
                            <ArrowLeft className="size-4" /> Volver al
                            presupuesto
                        </Link>
                    </Button>
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Consulta de sólo lectura
                        </p>
                        <h1 className="mt-1 flex items-center gap-2 text-2xl font-semibold">
                            <BarChart3 className="size-6" /> Reportes de
                            Ingresos Propios {budget.fiscal_year}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Región {budget.region_code} · {budget.region_name}
                        </p>
                    </div>
                </header>

                {!props.has_initial_budget && (
                    <Card className="border-amber-300 dark:border-amber-800">
                        <CardContent className="pt-6 text-sm">
                            Aún no hay presupuesto inicial autorizado. Los
                            saldos aparecerán después de la autorización
                            explícita.
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Filtros presupuestales</CardTitle>
                        <CardDescription>
                            Acota el desglose por capítulo, partida o mes. La
                            consulta permanece en esta ventana.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-3">
                        <Filter label="Capítulo">
                            <select
                                className={selectClassName}
                                value={filters.applied.chapter_code ?? ''}
                                onChange={(event) =>
                                    applyFilter(
                                        'chapter_code',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">Todos los capítulos</option>
                                {filters.options.chapters.map((chapter) => (
                                    <option
                                        key={chapter.code}
                                        value={chapter.code}
                                    >
                                        {chapter.code} · {chapter.name}
                                    </option>
                                ))}
                            </select>
                        </Filter>
                        <Filter label="Partida">
                            <select
                                className={selectClassName}
                                value={filters.applied.specific_item_code ?? ''}
                                onChange={(event) =>
                                    applyFilter(
                                        'specific_item_code',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">Todas las partidas</option>
                                {filteredItems.map((item) => (
                                    <option key={item.code} value={item.code}>
                                        {item.code} · {item.name}
                                    </option>
                                ))}
                            </select>
                        </Filter>
                        <Filter label="Mes">
                            <select
                                className={selectClassName}
                                value={filters.applied.month ?? ''}
                                onChange={(event) =>
                                    applyFilter('month', event.target.value)
                                }
                            >
                                <option value="">Todos los meses</option>
                                {filters.options.months.map(({ value }) => (
                                    <option key={value} value={value}>
                                        {monthNames[value]}
                                    </option>
                                ))}
                            </select>
                        </Filter>
                    </CardContent>
                </Card>

                <section
                    className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
                    aria-label="Saldos presupuestales"
                >
                    {cards.map(([label, value]) => (
                        <SummaryCard
                            key={label}
                            label={label}
                            value={value}
                            accent={label === 'Disponible'}
                        />
                    ))}
                </section>

                <BudgetTable lines={props.lines} />

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <BarChart3 className="size-5" /> Planeado contra
                                ejercido
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3 sm:grid-cols-3">
                            <Metric
                                label="Planeado"
                                value={formatCents(
                                    props.planning_vs_execution
                                        .planned_amount_cents,
                                )}
                            />
                            <Metric
                                label="Pagado"
                                value={formatCents(
                                    props.planning_vs_execution
                                        .paid_amount_cents,
                                )}
                            />
                            <Metric
                                label="Avance"
                                value={
                                    props.planning_vs_execution
                                        .execution_percentage === null
                                        ? 'Sin base'
                                        : `${props.planning_vs_execution.execution_percentage}%`
                                }
                            />
                        </CardContent>
                    </Card>
                    <FuelCard fuel={props.fuel} />
                    <ModificationCard modifications={props.modifications} />
                    <DossierCard dossiers={props.expense_dossiers} />
                </div>
            </main>
        </>
    );
}

function Filter({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <label className="grid gap-2 text-sm font-medium">
            <span>{label}</span>
            {children}
        </label>
    );
}

function SummaryCard({
    label,
    value,
    accent = false,
}: {
    label: string;
    value: string;
    accent?: boolean;
}) {
    return (
        <Card
            className={
                accent
                    ? 'border-emerald-300 dark:border-emerald-800'
                    : undefined
            }
        >
            <CardHeader className="pb-2">
                <CardTitle className="text-sm text-muted-foreground">
                    {label}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-xl font-semibold tabular-nums">
                    {formatCents(value)}
                </p>
            </CardContent>
        </Card>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg border p-3">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 font-semibold tabular-nums">{value}</p>
        </div>
    );
}

function BudgetTable({ lines }: { lines: ReportLine[] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <WalletCards className="size-5" /> Desglose presupuestal
                </CardTitle>
                <CardDescription>
                    Saldos históricos por partida y mes.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {lines.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No hay movimientos para los filtros seleccionados.
                    </p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-5xl text-sm">
                            <thead>
                                <tr className="border-b text-left text-muted-foreground">
                                    <th className="px-2 py-3">Partida</th>
                                    <th className="px-2 py-3">Mes</th>
                                    {[
                                        'Inicial',
                                        'Modificado',
                                        'Reservado',
                                        'Comprometido',
                                        'Pagado',
                                        'Disponible',
                                    ].map((label) => (
                                        <th
                                            key={label}
                                            className="px-2 py-3 text-right"
                                        >
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {lines.map((line) => (
                                    <tr
                                        key={line.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="px-2 py-3">
                                            <p className="font-medium">
                                                {line.specific_item_code}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {line.specific_item_name}
                                            </p>
                                        </td>
                                        <td className="px-2 py-3">
                                            {monthNames[line.month]}
                                        </td>
                                        {[
                                            line.initial_amount_cents,
                                            line.modified_amount_cents,
                                            line.reserved_amount_cents,
                                            line.committed_amount_cents,
                                            line.paid_amount_cents,
                                            line.available_amount_cents,
                                        ].map((value, index) => (
                                            <td
                                                key={index}
                                                className="px-2 py-3 text-right tabular-nums"
                                            >
                                                {formatCents(value)}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function FuelCard({ fuel }: { fuel: Props['fuel'] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Fuel className="size-5" /> Fondo de combustible
                </CardTitle>
                <CardDescription>
                    Este resumen operativo no cambia con los filtros
                    presupuestales.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-3 sm:grid-cols-2">
                <Metric
                    label="Fondo adquirido"
                    value={formatCents(fuel.acquired_amount_cents)}
                />
                <Metric
                    label="Consumo confirmado"
                    value={formatCents(fuel.confirmed_consumption_cents)}
                />
                <Metric
                    label="Necesidades pendientes"
                    value={formatCents(fuel.pending_needs_cents)}
                />
                <Metric
                    label="Saldo disponible"
                    value={formatCents(fuel.available_amount_cents)}
                />
            </CardContent>
        </Card>
    );
}

function ModificationCard({
    modifications,
}: {
    modifications: Props['modifications'];
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <ArrowRightLeft className="size-5" /> Transferencias y
                    cambios de mes
                </CardTitle>
                <CardDescription>
                    {modifications.total} movimientos relacionados con el filtro
                    actual.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                <div className="grid gap-3 sm:grid-cols-2">
                    <Metric
                        label="Transferencias"
                        value={formatCents(modifications.transfer_amount_cents)}
                    />
                    <Metric
                        label="Recalendarizaciones"
                        value={formatCents(
                            modifications.rescheduling_amount_cents,
                        )}
                    />
                </div>
                {modifications.items.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No hay modificaciones para los filtros seleccionados.
                    </p>
                ) : (
                    <div className="grid gap-2">
                        {modifications.items.map((item) => (
                            <div
                                key={item.id}
                                className="rounded-lg border p-3 text-sm"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <Badge variant="outline">
                                        {item.type === 'transfer'
                                            ? 'Transferencia'
                                            : 'Cambio de mes'}
                                    </Badge>
                                    <strong>
                                        {formatCents(item.amount_cents)}
                                    </strong>
                                </div>
                                <p className="mt-2 text-muted-foreground">
                                    {item.source.specific_item_code} ·{' '}
                                    {monthNames[item.source.month]} →{' '}
                                    {item.destination.specific_item_code} ·{' '}
                                    {monthNames[item.destination.month]}
                                </p>
                                <p className="mt-1">{item.reason}</p>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function DossierCard({ dossiers }: { dossiers: Props['expense_dossiers'] }) {
    const visible = Object.entries(dossiers.by_status).filter(
        ([, count]) => count > 0,
    );

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <ClipboardList className="size-5" /> Expedientes por etapa
                </CardTitle>
                <CardDescription>
                    {dossiers.total} expedientes y{' '}
                    {dossiers.pending_requirements} requisitos pendientes.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {visible.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Todavía no hay expedientes para los filtros
                        seleccionados.
                    </p>
                ) : (
                    <div className="flex flex-wrap gap-2">
                        {visible.map(([status, count]) => (
                            <Badge key={status} variant="secondary">
                                {dossierLabels[status] ?? status}: {count}
                            </Badge>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
