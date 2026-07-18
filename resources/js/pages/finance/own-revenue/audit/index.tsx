import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Clock3, History, UserRound } from 'lucide-react';
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
import audit from '@/routes/finance/own-revenue/budgets/audit';

type AuditEvent = {
    id: string;
    type: string;
    occurred_at: string | null;
    title: string;
    description: string;
    actor_name: string | null;
    reference: string | null;
};

type Props = {
    budget: {
        id: number;
        fiscal_year: number;
        status: string;
        region_code: string;
        region_name: string;
    };
    timeline: {
        applied_type: string | null;
        options: Array<{ value: string; label: string }>;
        events: AuditEvent[];
    };
    permissions: { read_only: true };
};

const selectClassName =
    'h-10 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

function formatDate(value: string | null): string {
    if (value === null) {
        return 'Fecha no disponible';
    }

    return new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'America/Cancun',
    }).format(new Date(value));
}

export default function OwnRevenueAuditIndex({ budget, timeline }: Props) {
    const filterByType = (value: string): void => {
        router.get(
            audit.index.url(budget.id),
            value === '' ? {} : { type: value },
            { preserveState: true, replace: true },
        );
    };

    const labels = new Map(
        timeline.options.map((option) => [option.value, option.label]),
    );

    return (
        <>
            <Head
                title={`Historial de Ingresos Propios ${budget.fiscal_year}`}
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
                            Consulta de sólo lectura · Región{' '}
                            {budget.region_code} · {budget.region_name}
                        </p>
                        <h1 className="mt-1 flex items-center gap-2 text-2xl font-semibold">
                            <History className="size-6" /> Historial consolidado{' '}
                            {budget.fiscal_year}
                        </h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                            Reúne los principales cambios, autorizaciones y
                            operaciones del ejercicio en un solo lugar.
                        </p>
                    </div>
                </header>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Filtrar historial
                        </CardTitle>
                        <CardDescription>
                            Selecciona una parte del proceso o consulta todos
                            los movimientos.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <label className="grid max-w-md gap-2 text-sm font-medium">
                            Tipo de movimiento
                            <select
                                className={selectClassName}
                                value={timeline.applied_type ?? ''}
                                onChange={(event) =>
                                    filterByType(event.target.value)
                                }
                            >
                                <option value="">Todos los movimientos</option>
                                {timeline.options.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </CardContent>
                </Card>

                <section
                    aria-label="Movimientos del ejercicio"
                    className="grid gap-3"
                >
                    {timeline.events.map((event) => (
                        <Card key={event.id}>
                            <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <CardTitle className="text-base">
                                            {event.title}
                                        </CardTitle>
                                        <Badge variant="secondary">
                                            {labels.get(event.type) ??
                                                event.type}
                                        </Badge>
                                    </div>
                                    <CardDescription className="mt-2">
                                        {event.description}
                                    </CardDescription>
                                </div>
                                <p className="flex shrink-0 items-center gap-1.5 text-xs text-muted-foreground">
                                    <Clock3 className="size-3.5" />
                                    {formatDate(event.occurred_at)}
                                </p>
                            </CardHeader>
                            {(event.actor_name !== null ||
                                event.reference !== null) && (
                                <CardContent className="flex flex-wrap gap-x-5 gap-y-2 border-t pt-4 text-sm text-muted-foreground">
                                    {event.actor_name !== null && (
                                        <span className="flex items-center gap-1.5">
                                            <UserRound className="size-3.5" />
                                            {event.actor_name}
                                        </span>
                                    )}
                                    {event.reference !== null && (
                                        <span>
                                            Referencia:{' '}
                                            <span className="font-medium text-foreground">
                                                {event.reference}
                                            </span>
                                        </span>
                                    )}
                                </CardContent>
                            )}
                        </Card>
                    ))}

                    {timeline.events.length === 0 && (
                        <Card>
                            <CardContent className="py-10 text-center text-sm text-muted-foreground">
                                No hay movimientos para el filtro seleccionado.
                            </CardContent>
                        </Card>
                    )}
                </section>
            </main>
        </>
    );
}
