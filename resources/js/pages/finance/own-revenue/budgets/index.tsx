import { Head, Link } from '@inertiajs/react';
import { CalendarRange, FolderOpen, Plus } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { create, show } from '@/routes/finance/own-revenue/budgets';
import type {
    AnnualValueStatus,
    CogCatalogStatus,
    OwnRevenueBudgetListItem,
    OwnRevenueBudgetStatus,
    OwnRevenueIndexPermissions,
} from '@/types/finance-own-revenue';

type Props = {
    budgets: OwnRevenueBudgetListItem[];
    permissions: OwnRevenueIndexPermissions;
};

const budgetLabels: Record<OwnRevenueBudgetStatus, string> = {
    draft: 'Borrador',
    proposal_calculated: 'Propuesta calculada',
    proposal_adjusted: 'Propuesta ajustada',
    initial_authorized: 'Inicial autorizado',
    in_execution: 'En ejecución',
    closed: 'Cerrado',
};

const annualLabels: Record<AnnualValueStatus, string> = {
    pending_review: 'Pendiente',
    provisional: 'Provisional',
    final: 'Final',
};

const cogLabels: Record<CogCatalogStatus, string> = {
    pending_confirmation: 'Por confirmar',
    confirmed: 'Confirmado',
};

function badgeClass(
    status: AnnualValueStatus | CogCatalogStatus | OwnRevenueBudgetStatus,
): string {
    if (
        status === 'final' ||
        status === 'confirmed' ||
        status === 'in_execution'
    ) {
        return 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200';
    }

    if (status === 'pending_review' || status === 'pending_confirmation') {
        return 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200';
    }

    return 'border-border bg-muted text-muted-foreground';
}

function StatusBadge({
    label,
    status,
}: {
    label: string;
    status: AnnualValueStatus | CogCatalogStatus | OwnRevenueBudgetStatus;
}) {
    return (
        <Badge variant="outline" className={badgeClass(status)}>
            {label}
        </Badge>
    );
}

export default function OwnRevenueBudgetsIndex({
    budgets,
    permissions,
}: Props) {
    return (
        <>
            <Head title="Presupuesto de Ingresos Propios" />
            <main className="flex h-full flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Planeación financiera anual
                        </p>
                        <h1 className="text-2xl font-semibold">
                            Presupuesto de Ingresos Propios
                        </h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                            Consulta la fotografía institucional, parámetros UMA
                            y combustible, y el estado del catálogo COG de cada
                            ejercicio.
                        </p>
                    </div>
                    {permissions.create && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus className="size-4" />
                                Nuevo presupuesto
                            </Link>
                        </Button>
                    )}
                </header>

                {budgets.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex flex-col items-center gap-3 py-12 text-center">
                            <span className="rounded-full bg-muted p-3">
                                <CalendarRange className="size-6 text-muted-foreground" />
                            </span>
                            <div>
                                <h2 className="font-semibold">
                                    Aún no hay presupuestos anuales
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Crea el primer ejercicio para empezar la
                                    planeación de ingresos propios.
                                </p>
                            </div>
                            {permissions.create && (
                                <Button asChild size="sm">
                                    <Link href={create()}>
                                        Crear presupuesto
                                    </Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <section
                            className="hidden overflow-hidden rounded-lg border md:block"
                            aria-label="Presupuestos anuales"
                        >
                            <table className="w-full text-sm">
                                <thead className="bg-muted/60 text-left">
                                    <tr>
                                        <th className="px-4 py-3">Ejercicio</th>
                                        <th className="px-4 py-3">Región</th>
                                        <th className="px-4 py-3">
                                            Presupuesto
                                        </th>
                                        <th className="px-4 py-3">UMA</th>
                                        <th className="px-4 py-3">
                                            Combustible
                                        </th>
                                        <th className="px-4 py-3">COG</th>
                                        <th className="px-4 py-3 text-right">
                                            Acción
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {budgets.map((budget) => (
                                        <tr
                                            key={budget.id}
                                            className="border-t"
                                        >
                                            <td className="px-4 py-3 text-base font-semibold">
                                                {budget.fiscal_year}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="font-medium">
                                                    {budget.region.code}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {budget.region.name}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    status={budget.status}
                                                    label={
                                                        budgetLabels[
                                                            budget.status
                                                        ]
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    status={budget.uma.status}
                                                    label={
                                                        annualLabels[
                                                            budget.uma.status
                                                        ]
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    status={budget.fuel.status}
                                                    label={
                                                        annualLabels[
                                                            budget.fuel.status
                                                        ]
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    status={budget.cog.status}
                                                    label={
                                                        cogLabels[
                                                            budget.cog.status
                                                        ]
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link href={show(budget)}>
                                                        <FolderOpen className="size-4" />
                                                        Abrir
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </section>

                        <section
                            className="grid gap-3 md:hidden"
                            aria-label="Presupuestos anuales"
                        >
                            {budgets.map((budget) => (
                                <Card key={budget.id}>
                                    <CardHeader className="flex-row items-start justify-between gap-3">
                                        <div>
                                            <CardTitle>
                                                Ejercicio {budget.fiscal_year}
                                            </CardTitle>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {budget.region.code} ·{' '}
                                                {budget.region.name}
                                            </p>
                                        </div>
                                        <StatusBadge
                                            status={budget.status}
                                            label={budgetLabels[budget.status]}
                                        />
                                    </CardHeader>
                                    <CardContent className="grid gap-4">
                                        <dl className="grid grid-cols-3 gap-2">
                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    UMA
                                                </dt>
                                                <dd className="mt-1">
                                                    <StatusBadge
                                                        status={
                                                            budget.uma.status
                                                        }
                                                        label={
                                                            annualLabels[
                                                                budget.uma
                                                                    .status
                                                            ]
                                                        }
                                                    />
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    Combustible
                                                </dt>
                                                <dd className="mt-1">
                                                    <StatusBadge
                                                        status={
                                                            budget.fuel.status
                                                        }
                                                        label={
                                                            annualLabels[
                                                                budget.fuel
                                                                    .status
                                                            ]
                                                        }
                                                    />
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-xs text-muted-foreground">
                                                    COG
                                                </dt>
                                                <dd className="mt-1">
                                                    <StatusBadge
                                                        status={
                                                            budget.cog.status
                                                        }
                                                        label={
                                                            cogLabels[
                                                                budget.cog
                                                                    .status
                                                            ]
                                                        }
                                                    />
                                                </dd>
                                            </div>
                                        </dl>
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="w-full"
                                        >
                                            <Link href={show(budget)}>
                                                <FolderOpen className="size-4" />
                                                Abrir ejercicio
                                            </Link>
                                        </Button>
                                    </CardContent>
                                </Card>
                            ))}
                        </section>
                    </>
                )}
            </main>
        </>
    );
}
