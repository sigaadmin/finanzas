import { Form, Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    CircleAlert,
    CopyPlus,
    FileSpreadsheet,
    ShieldCheck,
} from 'lucide-react';
import CorrectionDialog from '@/components/finance/own-revenue/planning/correction-dialog';
import FuelNeedForm from '@/components/finance/own-revenue/planning/fuel-need-form';
import PlanningRecordList from '@/components/finance/own-revenue/planning/planning-record-list';
import {
    planningSectionQuery,
    planningVersionQuery,
} from '@/components/finance/own-revenue/planning/planning-state.js';
import TechnicalNeedForm from '@/components/finance/own-revenue/planning/technical-need-form';
import {
    TravelCommissionForm,
    TravelParticipantForm,
} from '@/components/finance/own-revenue/planning/travel-forms';
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
import planning from '@/routes/finance/own-revenue/budgets/planning';
import proposals from '@/routes/finance/own-revenue/budgets/proposals';
import fromImports from '@/routes/finance/own-revenue/budgets/proposals/from-imports';
import proposalRevisions from '@/routes/finance/own-revenue/budgets/proposals/revisions';
import type {
    PlanningBudget,
    PlanningCatalogs,
    PlanningPaginator,
    PlanningProposal,
    PlanningReadiness,
    PlanningSection,
    PlanningSelectedDetail,
    PlanningSummary,
    PlanningVersion,
} from '@/types/finance-own-revenue';

type Props = {
    budget: PlanningBudget;
    readiness: PlanningReadiness;
    proposal: PlanningProposal | null;
    versions: PlanningVersion[];
    section: PlanningSection;
    summaries: Record<PlanningSection, PlanningSummary>;
    rows: PlanningPaginator;
    selected_detail: PlanningSelectedDetail;
    catalogs: PlanningCatalogs;
    permissions: {
        create: boolean;
        edit: boolean;
        calculate: boolean;
        revise: boolean;
    };
};

const sectionLabels: Record<PlanningSection, string> = {
    technical: 'Ficha técnica',
    fuel: 'Combustible',
    travel: 'Viáticos',
};
const statusLabels = {
    draft: 'Borrador editable',
    calculated: 'Calculada',
    adjusted: 'Ajustada',
};

function money(cents: string): string {
    const value = cents.padStart(3, '0');
    const whole = value.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    return `$${whole}.${value.slice(-2)}`;
}

export default function OwnRevenuePlanningShow({
    budget,
    readiness,
    proposal,
    versions,
    section,
    summaries,
    rows,
    selected_detail: selectedDetail,
    catalogs,
    permissions,
}: Props) {
    const currentUrl =
        typeof window === 'undefined'
            ? ''
            : `${window.location.pathname}${window.location.search}`;
    const visit = (query: Record<string, string>) =>
        router.get(planning.show(budget.id).url, query, {
            preserveScroll: true,
        });
    const sourceIds = readiness.source_file_ids;

    return (
        <>
            <Head title={`Planeación ${budget.fiscal_year}`} />
            <main className="flex h-full flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="grid gap-3">
                    <Button asChild variant="ghost" size="sm" className="w-fit">
                        <Link href={budgets.show(budget.id)}>
                            <ArrowLeft className="size-4" />
                            Volver al presupuesto
                        </Link>
                    </Button>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Presupuesto de Ingresos Propios
                            </p>
                            <h1 className="text-2xl font-semibold">
                                Planeación {budget.fiscal_year}
                            </h1>
                        </div>
                        {proposal && (
                            <div className="flex flex-wrap items-center gap-2">
                                <Badge variant="outline">
                                    Versión {proposal.version_number}
                                </Badge>
                                <Badge>{statusLabels[proposal.status]}</Badge>
                                {permissions.calculate && (
                                    <Form
                                        action={
                                            proposals.calculate([
                                                budget.id,
                                                proposal.id,
                                            ]).url
                                        }
                                        method="post"
                                    >
                                        {({ processing, errors }) => (
                                            <div className="grid gap-1">
                                                <input
                                                    type="hidden"
                                                    name="proposal_fingerprint"
                                                    value={proposal.fingerprint}
                                                />
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    <ShieldCheck className="size-4" />
                                                    {processing
                                                        ? 'Calculando…'
                                                        : 'Calcular propuesta'}
                                                </Button>
                                                {(errors.proposal_fingerprint ||
                                                    errors.proposal) && (
                                                    <p className="max-w-72 text-xs text-destructive">
                                                        {errors.proposal_fingerprint ??
                                                            errors.proposal}
                                                    </p>
                                                )}
                                            </div>
                                        )}
                                    </Form>
                                )}
                                {permissions.revise && (
                                    <Form
                                        action={
                                            proposalRevisions.store([
                                                budget.id,
                                                proposal.id,
                                            ]).url
                                        }
                                        method="post"
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                size="sm"
                                                variant="outline"
                                                disabled={processing}
                                            >
                                                <CopyPlus className="size-4" />
                                                {processing
                                                    ? 'Creando…'
                                                    : 'Crear versión editable'}
                                            </Button>
                                        )}
                                    </Form>
                                )}
                            </div>
                        )}
                    </div>
                </header>

                {!proposal && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Antes de crear la propuesta</CardTitle>
                            <CardDescription>
                                La propuesta toma como base los cinco archivos
                                confirmados y la configuración anual revisada.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            {readiness.ready ? (
                                <p className="flex items-center gap-2 text-sm text-emerald-700 dark:text-emerald-300">
                                    <CheckCircle2 className="size-4" />
                                    Todo está listo para comenzar.
                                </p>
                            ) : (
                                <ul className="grid gap-2">
                                    {readiness.blockers.map((blocker) => (
                                        <li
                                            key={blocker}
                                            className="flex gap-2 text-sm"
                                        >
                                            <CircleAlert className="mt-0.5 size-4 shrink-0 text-amber-600" />
                                            {blocker}
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {permissions.create && readiness.ready && (
                                <Form
                                    action={fromImports.store(budget.id).url}
                                    method="post"
                                >
                                    {({ processing }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="source_abpre_file_id"
                                                value={sourceIds.abpre}
                                            />
                                            <input
                                                type="hidden"
                                                name="source_work_sheet_file_id"
                                                value={sourceIds.work_sheet}
                                            />
                                            <input
                                                type="hidden"
                                                name="source_technical_sheet_file_id"
                                                value={
                                                    sourceIds.technical_sheet
                                                }
                                            />
                                            <input
                                                type="hidden"
                                                name="source_fuel_file_id"
                                                value={sourceIds.fuel}
                                            />
                                            <input
                                                type="hidden"
                                                name="source_travel_expenses_file_id"
                                                value={
                                                    sourceIds.travel_expenses
                                                }
                                            />
                                            <input
                                                type="hidden"
                                                name="source_fingerprint"
                                                value={
                                                    readiness.source_fingerprint
                                                }
                                            />
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                <FileSpreadsheet className="size-4" />
                                                {processing
                                                    ? 'Creando…'
                                                    : 'Crear propuesta desde importaciones'}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            )}
                        </CardContent>
                    </Card>
                )}

                {proposal && (
                    <>
                        <section
                            className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                            aria-label="Totales de la propuesta"
                        >
                            {(
                                [
                                    'technical',
                                    'fuel',
                                    'travel',
                                ] as PlanningSection[]
                            ).map((key) => (
                                <Card key={key}>
                                    <CardHeader>
                                        <CardTitle className="text-sm text-muted-foreground">
                                            {sectionLabels[key]}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-xl font-semibold">
                                            {money(
                                                summaries[key]
                                                    .total_amount_cents,
                                            )}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {summaries[key].count} registro(s)
                                        </p>
                                    </CardContent>
                                </Card>
                            ))}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-sm text-muted-foreground">
                                        Total propuesto
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-xl font-semibold">
                                        {money(proposal.total_amount_cents)}
                                    </p>
                                </CardContent>
                            </Card>
                        </section>

                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    Versiones y archivos de origen
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 lg:grid-cols-2">
                                <div className="flex flex-wrap gap-2">
                                    {versions.map((version) => (
                                        <Button
                                            key={version.id}
                                            type="button"
                                            size="sm"
                                            variant={
                                                version.id === proposal.id
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            onClick={() =>
                                                visit(
                                                    planningVersionQuery(
                                                        currentUrl,
                                                        version.version_number,
                                                    ),
                                                )
                                            }
                                        >
                                            Versión {version.version_number} ·{' '}
                                            {statusLabels[version.status]}
                                        </Button>
                                    ))}
                                </div>
                                <dl className="grid gap-2 text-sm">
                                    {Object.entries(proposal.sources).map(
                                        ([label, value]) => (
                                            <div
                                                key={label}
                                                className="grid gap-0.5"
                                            >
                                                <dt className="font-medium">
                                                    {label}
                                                </dt>
                                                <dd className="text-muted-foreground">
                                                    {value ??
                                                        'Sin archivo de origen'}
                                                </dd>
                                            </div>
                                        ),
                                    )}
                                </dl>
                            </CardContent>
                        </Card>

                        <nav
                            className="flex gap-2 overflow-x-auto rounded-lg border p-1"
                            aria-label="Secciones de planeación"
                        >
                            {(
                                [
                                    'technical',
                                    'fuel',
                                    'travel',
                                ] as PlanningSection[]
                            ).map((key) => (
                                <Button
                                    key={key}
                                    type="button"
                                    variant={
                                        key === section ? 'default' : 'ghost'
                                    }
                                    onClick={() =>
                                        visit(
                                            planningSectionQuery(
                                                currentUrl,
                                                key,
                                            ),
                                        )
                                    }
                                >
                                    {sectionLabels[key]}
                                </Button>
                            ))}
                        </nav>
                        <PlanningRecordList
                            budgetId={budget.id}
                            proposalId={proposal.id}
                            section={section}
                            rows={rows}
                            editable={permissions.edit}
                        />
                        {permissions.edit && section === 'technical' && (
                            <TechnicalNeedForm
                                budgetId={budget.id}
                                proposalId={proposal.id}
                                catalogs={catalogs}
                            />
                        )}
                        {permissions.edit && section === 'fuel' && (
                            <FuelNeedForm
                                budgetId={budget.id}
                                proposalId={proposal.id}
                                catalogs={catalogs}
                            />
                        )}
                        {permissions.edit && section === 'travel' && (
                            <>
                                <TravelCommissionForm
                                    budgetId={budget.id}
                                    proposalId={proposal.id}
                                    catalogs={catalogs}
                                />
                                <TravelParticipantForm
                                    budgetId={budget.id}
                                    proposalId={proposal.id}
                                    commissions={rows.data}
                                />
                            </>
                        )}
                        {!permissions.edit && (
                            <Card>
                                <CardContent className="py-5 text-sm text-muted-foreground">
                                    Esta versión está disponible únicamente para
                                    consulta.
                                </CardContent>
                            </Card>
                        )}
                    </>
                )}
            </main>
            <CorrectionDialog detail={selectedDetail} />
        </>
    );
}
