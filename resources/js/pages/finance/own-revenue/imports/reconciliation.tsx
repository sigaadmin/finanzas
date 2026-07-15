import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, ListChecks } from 'lucide-react';
import { useState } from 'react';
import OwnRevenueActivityExceptionController from '@/actions/App/Http/Controllers/Finance/OwnRevenueActivityExceptionController';
import OwnRevenueActivityReconciliationController from '@/actions/App/Http/Controllers/Finance/OwnRevenueActivityReconciliationController';
import OwnRevenueActivityRuleController from '@/actions/App/Http/Controllers/Finance/OwnRevenueActivityRuleController';
import { formatCents } from '@/components/finance/own-revenue/imports/abpre-preview';
import {
    openActivityGroup,
    reconciliationStatusLabel,
} from '@/components/finance/own-revenue/imports/activity-reconciliation-state';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { show as showImports } from '@/routes/finance/own-revenue/budgets/imports';
import type {
    OwnRevenueActivityReconciliationGroup,
    OwnRevenueActivityReconciliationProps,
    OwnRevenueActivityReconciliationRecord,
    OwnRevenueReconciliationActivity,
    OwnRevenueSupportingFormat,
} from '@/types/finance-own-revenue-imports';

const formatOrder: OwnRevenueSupportingFormat[] = [
    'technical_sheet',
    'fuel',
    'travel_expenses',
];

const justificationOptions = [
    ['work_sheet_match', 'Coincide con la Hoja de trabajo'],
    ['description_classification', 'Clasificación por descripción o motivo'],
    ['administrative_criterion', 'Criterio administrativo'],
    ['other', 'Otro'],
] as const;

function ActivityFields({
    activities,
    activityId,
    justification,
    note,
    setActivity,
    setJustification,
    setNote,
}: {
    activities: OwnRevenueReconciliationActivity[];
    activityId: number | '';
    justification: string;
    note: string;
    setActivity: (value: number) => void;
    setJustification: (value: string) => void;
    setNote: (value: string) => void;
}) {
    return (
        <div className="grid gap-3">
            <div className="grid gap-1.5">
                <Label>Actividad</Label>
                <select
                    value={activityId}
                    onChange={(event) =>
                        setActivity(Number(event.target.value))
                    }
                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                    required
                >
                    <option value="">Selecciona una actividad</option>
                    {activities.map((activity) => (
                        <option key={activity.id} value={activity.id}>
                            {activity.code} · {activity.name}
                        </option>
                    ))}
                </select>
            </div>
            <div className="grid gap-1.5">
                <Label>Motivo de la asignación</Label>
                <select
                    value={justification}
                    onChange={(event) => setJustification(event.target.value)}
                    className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                    required
                >
                    {justificationOptions.map(([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </select>
            </div>
            {justification === 'other' && (
                <div className="grid gap-1.5">
                    <Label>Explicación</Label>
                    <textarea
                        value={note}
                        onChange={(event) => setNote(event.target.value)}
                        maxLength={2000}
                        required
                        rows={3}
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                </div>
            )}
        </div>
    );
}

function RuleForm({
    budgetId,
    format,
    group,
    snapshots,
}: Pick<OwnRevenueActivityReconciliationProps, 'snapshots'> & {
    budgetId: number;
    format: OwnRevenueSupportingFormat;
    group: OwnRevenueActivityReconciliationGroup;
}) {
    const activities = group.candidates;
    const form = useForm({
        format,
        group_hash: group.hash,
        activity_id:
            group.current_activity?.id ??
            group.suggested_activity_id ??
            ('' as number | ''),
        justification: 'description_classification',
        justification_note: '',
        expected_work_sheet_file_id: snapshots.work_sheet_file_id ?? 0,
        expected_supporting_file_id: snapshots.supporting_file_ids[format] ?? 0,
    });

    return (
        <form
            className="grid gap-3 rounded-lg border bg-muted/30 p-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(OwnRevenueActivityRuleController.url(budgetId), {
                    preserveScroll: true,
                });
            }}
        >
            <div>
                <p className="font-medium">Asignar a todo el grupo</p>
                <p className="text-sm text-muted-foreground">
                    La selección se reutilizará en futuras versiones del mismo
                    formato.
                </p>
            </div>
            <ActivityFields
                activities={activities}
                activityId={form.data.activity_id}
                justification={form.data.justification}
                note={form.data.justification_note}
                setActivity={(value) => form.setData('activity_id', value)}
                setJustification={(value) =>
                    form.setData('justification', value)
                }
                setNote={(value) => form.setData('justification_note', value)}
            />
            <InputError message={Object.values(form.errors)[0]} />
            <Button
                type="submit"
                disabled={form.processing || activities.length === 0}
            >
                {form.processing ? 'Guardando…' : 'Aplicar al grupo'}
            </Button>
        </form>
    );
}

function RecordExceptionForm({
    budgetId,
    format,
    record,
    activities,
    snapshots,
}: Pick<OwnRevenueActivityReconciliationProps, 'snapshots'> & {
    budgetId: number;
    format: OwnRevenueSupportingFormat;
    record: OwnRevenueActivityReconciliationRecord;
    activities: OwnRevenueReconciliationActivity[];
}) {
    const form = useForm({
        format,
        activity_id: record.activity?.id ?? ('' as number | ''),
        justification: 'administrative_criterion',
        justification_note: '',
        expected_work_sheet_file_id: snapshots.work_sheet_file_id ?? 0,
        expected_supporting_file_id: snapshots.supporting_file_ids[format] ?? 0,
    });

    return (
        <form
            className="grid gap-3 border-t pt-3"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(
                    OwnRevenueActivityExceptionController.url({
                        budget: budgetId,
                        record: record.id,
                    }),
                    { preserveScroll: true },
                );
            }}
        >
            <ActivityFields
                activities={activities}
                activityId={form.data.activity_id}
                justification={form.data.justification}
                note={form.data.justification_note}
                setActivity={(value) => form.setData('activity_id', value)}
                setJustification={(value) =>
                    form.setData('justification', value)
                }
                setNote={(value) => form.setData('justification_note', value)}
            />
            <InputError message={Object.values(form.errors)[0]} />
            <Button
                type="submit"
                size="sm"
                variant="outline"
                disabled={form.processing}
            >
                {form.processing ? 'Guardando…' : 'Guardar excepción'}
            </Button>
        </form>
    );
}

export default function OwnRevenueActivityReconciliation(
    props: OwnRevenueActivityReconciliationProps,
) {
    const {
        budget,
        summary,
        snapshots,
        formats,
        selected_format: selectedFormat,
        groups,
        selected_group: selectedGroup,
        empty_reasons: emptyReasons,
        permissions,
    } = props;
    const currentUrl = usePage().url;
    const [detailOpen, setDetailOpen] = useState(selectedGroup !== null);

    const openGroup = (hash: string): void => {
        router.get(
            OwnRevenueActivityReconciliationController.url(budget.id),
            openActivityGroup(currentUrl, hash),
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => setDetailOpen(true),
            },
        );
    };

    return (
        <>
            <Head title={`Conciliar actividades ${budget.fiscal_year}`} />
            <main className="flex h-full flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="grid gap-3">
                    <Button asChild variant="ghost" size="sm" className="w-fit">
                        <Link href={showImports(budget.id)}>
                            <ArrowLeft className="size-4" />
                            Volver a importaciones
                        </Link>
                    </Button>
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Presupuesto de Ingresos Propios
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold">
                            Conciliar actividades
                        </h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                            Asigna cada grupo a una actividad del presupuesto y
                            revisa excepciones puntuales.
                        </p>
                    </div>
                </header>

                <section
                    className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                    aria-label="Avance de conciliación"
                >
                    <Card>
                        <CardHeader className="pb-2">
                            <CardDescription>Avance general</CardDescription>
                            <CardTitle>
                                {summary.assigned} de {summary.total}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm text-muted-foreground">
                            {reconciliationStatusLabel(summary)}
                        </CardContent>
                    </Card>
                    {formatOrder.map((format) => (
                        <Card key={format}>
                            <CardHeader className="pb-2">
                                <CardDescription>
                                    {formats[format].label}
                                </CardDescription>
                                <CardTitle>
                                    {formats[format].summary.assigned} de{' '}
                                    {formats[format].summary.total}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="text-sm text-muted-foreground">
                                {reconciliationStatusLabel(
                                    formats[format].summary,
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </section>

                {Object.values(emptyReasons).map((reason) => (
                    <p
                        key={reason}
                        className="rounded-lg border border-amber-300/60 bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-950/30 dark:text-amber-100"
                    >
                        {reason}
                    </p>
                ))}

                <nav
                    className="flex flex-wrap gap-2"
                    aria-label="Formatos complementarios"
                >
                    {formatOrder.map((format) => (
                        <Button
                            key={format}
                            asChild
                            variant={
                                format === selectedFormat
                                    ? 'default'
                                    : 'outline'
                            }
                        >
                            <Link
                                href={OwnRevenueActivityReconciliationController.url(
                                    budget.id,
                                    { query: { format } },
                                )}
                            >
                                {formats[format].label}
                            </Link>
                        </Button>
                    ))}
                </nav>

                <Card>
                    <CardHeader>
                        <CardTitle>{formats[selectedFormat].label}</CardTitle>
                        <CardDescription>
                            Archivo:{' '}
                            {formatCents(formats[selectedFormat].detail_cents)}{' '}
                            · Hoja de trabajo:{' '}
                            {formatCents(
                                formats[selectedFormat].work_sheet_cents,
                            )}{' '}
                            · Diferencia:{' '}
                            {formatCents(
                                formats[selectedFormat].difference_cents,
                            )}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {groups.data.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No hay grupos confirmados en este formato.
                            </p>
                        ) : (
                            groups.data.map((group) => (
                                <div
                                    key={group.hash}
                                    className="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="min-w-0">
                                        <p className="font-medium">
                                            {group.label}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {group.record_count}{' '}
                                            {group.record_count === 1
                                                ? 'registro'
                                                : 'registros'}{' '}
                                            · {formatCents(group.detail_cents)}
                                        </p>
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            <Badge
                                                variant={
                                                    group.summary.complete
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                            >
                                                {reconciliationStatusLabel(
                                                    group.summary,
                                                )}
                                            </Badge>
                                            {group.current_activity && (
                                                <Badge variant="secondary">
                                                    {
                                                        group.current_activity
                                                            .code
                                                    }{' '}
                                                    ·{' '}
                                                    {
                                                        group.current_activity
                                                            .name
                                                    }
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => openGroup(group.hash)}
                                    >
                                        <ListChecks className="size-4" />
                                        Ver detalle
                                    </Button>
                                </div>
                            ))
                        )}
                        {groups.last_page > 1 && (
                            <nav
                                className="flex flex-wrap justify-center gap-2"
                                aria-label="Páginas de grupos"
                            >
                                {groups.links.map((link, index) =>
                                    link.url ? (
                                        <Button
                                            key={`${link.label}-${index}`}
                                            asChild
                                            size="sm"
                                            variant={
                                                link.active
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                        >
                                            <Link
                                                href={link.url}
                                                preserveScroll
                                                dangerouslySetInnerHTML={{
                                                    __html: link.label,
                                                }}
                                            />
                                        </Button>
                                    ) : null,
                                )}
                            </nav>
                        )}
                    </CardContent>
                </Card>
            </main>

            <Dialog
                open={detailOpen && selectedGroup !== null}
                onOpenChange={setDetailOpen}
            >
                <DialogContent className="max-h-[90vh] overflow-hidden p-0 sm:max-w-3xl lg:max-w-5xl">
                    {selectedGroup && (
                        <>
                            <DialogHeader className="border-b px-6 pt-6 pr-12 pb-4">
                                <DialogTitle>{selectedGroup.label}</DialogTitle>
                                <DialogDescription>
                                    {selectedGroup.record_count}{' '}
                                    {selectedGroup.record_count === 1
                                        ? 'registro'
                                        : 'registros'}{' '}
                                    · {formatCents(selectedGroup.detail_cents)}
                                </DialogDescription>
                            </DialogHeader>
                            <div className="grid max-h-[calc(90vh-9rem)] gap-4 overflow-y-auto px-4 pb-6 sm:px-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                                <div className="grid gap-3">
                                    {(selectedGroup.records ?? []).map(
                                        (record) => (
                                            <Card key={record.id}>
                                                <CardHeader className="pb-3">
                                                    <CardTitle className="text-base">
                                                        {record.label}
                                                    </CardTitle>
                                                    <CardDescription>
                                                        {formatCents(
                                                            record.amount_cents,
                                                        )}
                                                    </CardDescription>
                                                </CardHeader>
                                                <CardContent className="grid gap-3">
                                                    <p className="text-sm">
                                                        {record.activity ? (
                                                            <>
                                                                <CheckCircle2 className="mr-1 inline size-4 text-emerald-600" />
                                                                {
                                                                    record
                                                                        .activity
                                                                        .code
                                                                }{' '}
                                                                ·{' '}
                                                                {
                                                                    record
                                                                        .activity
                                                                        .name
                                                                }
                                                            </>
                                                        ) : (
                                                            'Actividad pendiente'
                                                        )}
                                                    </p>
                                                    {permissions.manage && (
                                                        <RecordExceptionForm
                                                            budgetId={budget.id}
                                                            format={
                                                                selectedFormat
                                                            }
                                                            record={record}
                                                            activities={
                                                                selectedGroup.candidates
                                                            }
                                                            snapshots={
                                                                snapshots
                                                            }
                                                        />
                                                    )}
                                                </CardContent>
                                            </Card>
                                        ),
                                    )}
                                </div>
                                <aside>
                                    {permissions.manage ? (
                                        <RuleForm
                                            key={selectedGroup.hash}
                                            budgetId={budget.id}
                                            format={selectedFormat}
                                            group={selectedGroup}
                                            snapshots={snapshots}
                                        />
                                    ) : (
                                        <p className="rounded-lg border p-4 text-sm text-muted-foreground">
                                            Esta vista es de consulta. Puedes
                                            revisar las asignaciones, pero no
                                            modificarlas.
                                        </p>
                                    )}
                                </aside>
                            </div>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
