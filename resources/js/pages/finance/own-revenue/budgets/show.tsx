import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRightLeft,
    CheckCircle2,
    ClipboardList,
    Copy,
    Database,
    FileSpreadsheet,
    Fuel,
    MapPin,
    Scale,
    Users,
} from 'lucide-react';
import AnnualSettingsForm, {
    centsToPesos,
} from '@/components/finance/own-revenue/annual-settings-form';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { create, index } from '@/routes/finance/own-revenue/budgets';
import { confirm as confirmCog } from '@/routes/finance/own-revenue/budgets/cog';
import execution from '@/routes/finance/own-revenue/budgets/execution';
import imports from '@/routes/finance/own-revenue/budgets/imports';
import planning from '@/routes/finance/own-revenue/budgets/planning';
import type {
    AnnualValueStatus,
    CogCatalogStatus,
    OwnRevenueBudgetDetail,
    OwnRevenueBudgetStatus,
    OwnRevenueDetailPermissions,
} from '@/types/finance-own-revenue';

type Props = {
    budget: OwnRevenueBudgetDetail;
    permissions: OwnRevenueDetailPermissions;
    import_summary: {
        confirmed: number;
        missing: number;
        parser_pending: number;
    };
};

type CogConfirmationFormData = {
    catalog?: string;
    confirmed_by?: string;
    cog?: string;
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
    pending_review: 'Pendiente de revisión',
    provisional: 'Provisional',
    final: 'Final',
};

const cogLabels: Record<CogCatalogStatus, string> = {
    pending_confirmation: 'Pendiente de confirmación',
    confirmed: 'Confirmado',
};

function statusClass(
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
    status,
    label,
}: {
    status: AnnualValueStatus | CogCatalogStatus | OwnRevenueBudgetStatus;
    label: string;
}) {
    return (
        <Badge variant="outline" className={statusClass(status)}>
            {label}
        </Badge>
    );
}

function displayDecimal(value: string | null, suffix = ''): string {
    return value === null ? 'Pendiente' : `${value}${suffix}`;
}

function displayCents(cents: string | null): string {
    if (cents === null) {
        return 'Pendiente';
    }

    const [whole, fraction] = centsToPesos(cents).split('.');
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    return `$${grouped}.${fraction}`;
}

function displayDate(value: string | null): string {
    if (value === null) {
        return 'Sin registro';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'Fecha no disponible';
    }

    return new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'America/Cancun',
    }).format(date);
}

export default function OwnRevenueBudgetShow({
    budget,
    permissions,
    import_summary: importSummary,
}: Props) {
    const cogForm = useForm<CogConfirmationFormData>({});
    const settings = budget.settings;
    const canConfirmCog =
        permissions.confirmCog &&
        budget.cog.status === 'pending_confirmation' &&
        budget.cog.row_count > 0;

    const submitCogConfirmation = (): void => {
        if (
            !window.confirm(
                `Confirmar ${budget.cog.row_count.toLocaleString('es-MX')} filas COG para ${budget.fiscal_year}. Esta confirmación quedará auditada.`,
            )
        ) {
            return;
        }

        cogForm.post(confirmCog(budget.id).url, { preserveScroll: true });
    };

    return (
        <>
            <Head title={`Ingresos propios ${budget.fiscal_year}`} />
            <main className="flex h-full flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="grid gap-3">
                    <Button asChild variant="ghost" size="sm" className="w-fit">
                        <Link href={index()}>
                            <ArrowLeft className="size-4" />
                            Volver al listado
                        </Link>
                    </Button>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Presupuesto de Ingresos Propios
                            </p>
                            <div className="mt-1 flex flex-wrap items-center gap-2">
                                <h1 className="text-2xl font-semibold">
                                    Ejercicio {budget.fiscal_year}
                                </h1>
                                <StatusBadge
                                    status={budget.status}
                                    label={budgetLabels[budget.status]}
                                />
                            </div>
                        </div>
                        {permissions.copy && (
                            <Button asChild variant="outline">
                                <Link
                                    href={create({
                                        query: { source_budget_id: budget.id },
                                    })}
                                >
                                    <Copy className="size-4" />
                                    Crear una copia
                                </Link>
                            </Button>
                        )}
                    </div>
                </header>

                <section
                    className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                    aria-label="Resumen anual"
                >
                    <SummaryCard
                        icon={MapPin}
                        title="Región fija"
                        value={`${settings.region_code} · ${settings.region_name}`}
                        detail="No editable para este presupuesto"
                    />
                    <SummaryCard
                        icon={Scale}
                        title="UMA"
                        value={displayDecimal(settings.uma_value)}
                        detail={
                            <StatusBadge
                                status={settings.uma_status}
                                label={annualLabels[settings.uma_status]}
                            />
                        }
                    />
                    <SummaryCard
                        icon={Fuel}
                        title="Combustible"
                        value={displayDecimal(
                            settings.fuel_price_per_liter,
                            ' por litro',
                        )}
                        detail={
                            <>
                                <StatusBadge
                                    status={settings.fuel_price_status}
                                    label={
                                        annualLabels[settings.fuel_price_status]
                                    }
                                />
                                <span className="text-xs text-muted-foreground">
                                    Mes presupuestal: abril
                                </span>
                            </>
                        }
                    />
                    <SummaryCard
                        icon={Database}
                        title="Catálogo COG"
                        value={`${budget.cog.row_count.toLocaleString('es-MX')} filas`}
                        detail={
                            <StatusBadge
                                status={budget.cog.status}
                                label={cogLabels[budget.cog.status]}
                            />
                        }
                    />
                </section>

                <section className="grid gap-3 md:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm text-muted-foreground">
                                Ingreso estimado
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-xl font-semibold">
                                {displayCents(settings.estimated_income_cents)}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm text-muted-foreground">
                                Recorte
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-xl font-semibold">
                                {displayDecimal(settings.cut_percentage, '%')}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm text-muted-foreground">
                                Programa
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="font-semibold">
                                {settings.budget_program_code}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {settings.budget_program_name}
                            </p>
                        </CardContent>
                    </Card>
                </section>

                {permissions.viewImports && (
                    <Card>
                        <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <FileSpreadsheet className="size-5" />
                                    Importaciones XLSX
                                </CardTitle>
                                <CardDescription>
                                    Estado de los cinco formatos documentales
                                    del ejercicio.
                                </CardDescription>
                            </div>
                            <Button asChild variant="outline">
                                <Link href={imports.show(budget.id)}>
                                    Abrir espacio de importación
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="grid gap-3 sm:grid-cols-3">
                            <ImportSummaryItem
                                label="Confirmados"
                                value={importSummary.confirmed}
                                className="border-emerald-300 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/30"
                            />
                            <ImportSummaryItem
                                label="Faltantes"
                                value={importSummary.missing}
                                className="border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30"
                            />
                            <ImportSummaryItem
                                label="Revisión no disponible"
                                value={importSummary.parser_pending}
                                className="bg-muted/50"
                            />
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <ClipboardList className="size-5" />
                                Planeación editable
                            </CardTitle>
                            <CardDescription>
                                Revisa y captura Ficha técnica, Combustible y
                                Viáticos sin salir del sistema.
                            </CardDescription>
                        </div>
                        <Button asChild>
                            <Link href={planning.show(budget.id)}>
                                Abrir Planeación
                            </Link>
                        </Button>
                    </CardHeader>
                </Card>

                {['initial_authorized', 'in_execution', 'closed'].includes(
                    budget.status,
                ) && (
                    <Card>
                        <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <ArrowRightLeft className="size-5" />
                                    Presupuesto modificado
                                </CardTitle>
                                <CardDescription>
                                    Consulta los saldos y registra
                                    transferencias o cambios de mes sin alterar
                                    el presupuesto inicial.
                                </CardDescription>
                            </div>
                            <Button asChild>
                                <Link href={execution.show(budget.id)}>
                                    Abrir presupuesto modificado
                                </Link>
                            </Button>
                        </CardHeader>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>
                            Fotografía institucional del ejercicio
                        </CardTitle>
                        <CardDescription>
                            Configuración general registrada para este
                            presupuesto. Se muestra completa también en modo de
                            consulta.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-x-6 gap-y-4 sm:grid-cols-2 xl:grid-cols-3">
                            <ConfigurationItem
                                label="Institución"
                                value={settings.institution_name}
                                className="sm:col-span-2 xl:col-span-3"
                            />
                            <ConfigurationItem
                                label="Unidad responsable"
                                value={`${settings.responsible_unit_code} · ${settings.responsible_unit_name}`}
                            />
                            <ConfigurationItem
                                label="Programa presupuestario"
                                value={`${settings.budget_program_code} · ${settings.budget_program_name}`}
                            />
                            <ConfigurationItem
                                label="Componente presupuestario"
                                value={`${settings.component_code} · ${settings.component_name}`}
                            />
                            <ConfigurationItem
                                label="Actividad oficial"
                                value={`${settings.official_activity_code} · ${settings.official_activity_name}`}
                            />
                            <ConfigurationItem
                                label="Región fija"
                                value={`${settings.region_code} · ${settings.region_name}`}
                            />
                            <ConfigurationItem
                                label="Mes presupuestal de combustible"
                                value="Abril"
                            />
                            <ConfigurationItem
                                label="Ingreso estimado"
                                value={displayCents(
                                    settings.estimated_income_cents,
                                )}
                            />
                            <ConfigurationItem
                                label="Porcentaje de recorte"
                                value={displayDecimal(
                                    settings.cut_percentage,
                                    '%',
                                )}
                            />
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Auditoría del presupuesto</CardTitle>
                        <CardDescription>
                            Fechas generales de alta y última modificación del
                            ejercicio.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 sm:grid-cols-2">
                            <ConfigurationItem
                                label="Creado"
                                value={displayDate(budget.created_at)}
                            />
                            <ConfigurationItem
                                label="Última actualización"
                                value={displayDate(budget.updated_at)}
                            />
                        </dl>
                    </CardContent>
                </Card>

                <div className="grid gap-4 xl:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Actividades A01–A04</CardTitle>
                            <CardDescription>
                                Catálogo canónico del ejercicio.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <table className="w-full min-w-md text-sm">
                                <thead className="border-b text-left text-muted-foreground">
                                    <tr>
                                        <th scope="col" className="py-2 pr-4">
                                            Clave
                                        </th>
                                        <th scope="col" className="py-2">
                                            Actividad
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {budget.activities.map((activity) => (
                                        <tr
                                            key={activity.id}
                                            className="border-b last:border-0"
                                        >
                                            <td className="py-3 pr-4 font-medium">
                                                {activity.code}
                                            </td>
                                            <td className="py-3">
                                                {activity.name}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Users className="size-4" />
                                Firmantes
                            </CardTitle>
                            <CardDescription>
                                Orden de preparación, revisión y autorización.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            {budget.signatories.map((signatory) => (
                                <div
                                    key={signatory.id}
                                    className="rounded-lg border p-3"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="font-medium">
                                            {[
                                                signatory.academic_degree,
                                                signatory.name,
                                            ]
                                                .filter(Boolean)
                                                .join(' ')}
                                        </p>
                                        <Badge variant="secondary">
                                            {signatory.role_key}
                                        </Badge>
                                    </div>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {signatory.position}
                                    </p>
                                </div>
                            ))}
                            {budget.signatories.length === 0 && (
                                <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                                    No hay firmantes capturados.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Confirmación del catálogo COG</CardTitle>
                        <CardDescription>
                            {budget.cog.source_year === null
                                ? 'Catálogo inicial del ejercicio.'
                                : `Copiado del ejercicio ${budget.cog.source_year}.`}{' '}
                            La confirmación acredita que sus filas fueron
                            revisadas.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            {budget.cog.status === 'confirmed' &&
                            budget.cog.confirmed_by &&
                            budget.cog.confirmed_at ? (
                                <div className="flex gap-2 text-sm">
                                    <CheckCircle2 className="mt-0.5 size-4 text-emerald-600" />
                                    <p>
                                        Confirmado por{' '}
                                        <span className="font-medium">
                                            {budget.cog.confirmed_by.name}
                                        </span>
                                        <span className="block text-muted-foreground">
                                            {displayDate(
                                                budget.cog.confirmed_at,
                                            )}
                                        </span>
                                    </p>
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    {budget.cog.row_count === 0
                                        ? 'No hay filas COG disponibles para confirmar.'
                                        : 'El catálogo aún requiere confirmación.'}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            {canConfirmCog && (
                                <Button
                                    type="button"
                                    onClick={submitCogConfirmation}
                                    disabled={cogForm.processing}
                                    aria-describedby={
                                        cogForm.errors.catalog ||
                                        cogForm.errors.confirmed_by
                                            ? 'cog-confirm-error'
                                            : undefined
                                    }
                                >
                                    <CheckCircle2 className="size-4" />
                                    {cogForm.processing
                                        ? 'Confirmando…'
                                        : 'Confirmar catálogo COG'}
                                </Button>
                            )}
                            <InputError
                                id="cog-confirm-error"
                                role="alert"
                                message={
                                    cogForm.errors.catalog ??
                                    cogForm.errors.confirmed_by ??
                                    cogForm.errors.cog
                                }
                            />
                        </div>
                    </CardContent>
                </Card>

                {permissions.updateSettings &&
                ['draft', 'proposal_calculated', 'proposal_adjusted'].includes(
                    budget.status,
                ) ? (
                    <section className="grid gap-3">
                        <div>
                            <h2 className="text-xl font-semibold">
                                Editar configuración anual
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {budget.status === 'draft'
                                    ? 'Actualiza la fotografía institucional, parámetros y firmantes mientras el presupuesto esté en borrador.'
                                    : 'Corregir la fotografía institucional creará una nueva versión de la propuesta para recalcular y conciliar antes de autorizar.'}
                            </p>
                        </div>
                        <AnnualSettingsForm
                            budgetId={budget.id}
                            settings={settings}
                            signatories={budget.signatories}
                            institutionalOnly={budget.status !== 'draft'}
                        />
                    </section>
                ) : (
                    <Card>
                        <CardContent className="py-5 text-sm text-muted-foreground">
                            {budget.status === 'initial_authorized' ||
                            budget.status === 'in_execution' ||
                            budget.status === 'closed'
                                ? 'La fotografía institucional está bloqueada porque el presupuesto inicial ya fue autorizado.'
                                : 'Tienes acceso de consulta. La configuración de este ejercicio es de sólo lectura.'}
                        </CardContent>
                    </Card>
                )}
            </main>
        </>
    );
}

type SummaryCardProps = {
    icon: typeof MapPin;
    title: string;
    value: string;
    detail: React.ReactNode;
};

function SummaryCard({ icon: Icon, title, value, detail }: SummaryCardProps) {
    return (
        <Card>
            <CardHeader className="flex-row items-center gap-2">
                <span className="rounded-md bg-muted p-2">
                    <Icon className="size-4" />
                </span>
                <CardTitle className="text-sm text-muted-foreground">
                    {title}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p className="font-semibold">{value}</p>
                <div className="mt-2 flex flex-wrap items-center gap-2">
                    {detail}
                </div>
            </CardContent>
        </Card>
    );
}

function ImportSummaryItem({
    label,
    value,
    className,
}: {
    label: string;
    value: number;
    className: string;
}) {
    return (
        <div className={`rounded-lg border p-3 ${className}`}>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-xl font-semibold">{value} de 5</p>
        </div>
    );
}

function ConfigurationItem({
    label,
    value,
    className,
}: {
    label: string;
    value: string;
    className?: string;
}) {
    return (
        <div className={className}>
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="mt-1 font-medium">{value}</dd>
        </div>
    );
}
