import { Link, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, Check, CircleAlert, LoaderCircle, X } from 'lucide-react';
import OwnRevenueImportDecisionController from '@/actions/App/Http/Controllers/Finance/OwnRevenueImportDecisionController';
import OwnRevenueWorkSheetConfirmationController from '@/actions/App/Http/Controllers/Finance/OwnRevenueWorkSheetConfirmationController';
import {
    canConfirmWorkSheet,
    canManageWorkSheetDecision,
    formatCents,
    previewPageQuery,
    previewStateMessage,
    workSheetDecisionFeedback,
    workSheetConfirmationFeedback,
} from '@/components/finance/own-revenue/imports/work-sheet-preview-state';
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
import { Label } from '@/components/ui/label';
import { preview as showPreview } from '@/routes/finance/own-revenue/budgets/imports/files';
import type {
    LengthAwarePaginator,
    OwnRevenueImportFile,
    OwnRevenueImportPermissions,
    OwnRevenueWorkSheetIssue,
    OwnRevenueWorkSheetPreviewProps,
    OwnRevenueWorkSheetPreviewRow,
} from '@/types/finance-own-revenue-imports';

type Props = {
    budgetId: number;
    file: OwnRevenueImportFile;
    preview: LengthAwarePaginator<OwnRevenueWorkSheetPreviewRow>;
    blockingIssues: LengthAwarePaginator<OwnRevenueWorkSheetIssue>;
    reviewIssues: LengthAwarePaginator<OwnRevenueWorkSheetIssue>;
    viewState: OwnRevenueWorkSheetPreviewProps['view_state'];
    decisionsEnabled: boolean;
    canConfirm: boolean;
    confirmReasons: string[];
    permissions: OwnRevenueImportPermissions;
};

const months = [
    ['1', 'Enero'],
    ['2', 'Febrero'],
    ['3', 'Marzo'],
    ['4', 'Abril'],
    ['5', 'Mayo'],
    ['6', 'Junio'],
    ['7', 'Julio'],
    ['8', 'Agosto'],
    ['9', 'Septiembre'],
    ['10', 'Octubre'],
    ['11', 'Noviembre'],
    ['12', 'Diciembre'],
] as const;

function Pagination({
    paginator,
    pageName,
    label,
    budgetId,
    fileId,
}: {
    paginator: LengthAwarePaginator<unknown>;
    pageName: 'preview_page' | 'blocking_page' | 'review_page';
    label: string;
    budgetId: number;
    fileId: number;
}) {
    const currentUrl = usePage().url;

    if (paginator.last_page <= 1) {
        return null;
    }

    const route = (page: number) =>
        showPreview(
            { budget: budgetId, importFile: fileId },
            { query: previewPageQuery(currentUrl, pageName, page) },
        );

    return (
        <nav
            className="flex items-center justify-between gap-2"
            aria-label={label}
        >
            {paginator.current_page > 1 ? (
                <Button asChild size="sm" variant="outline">
                    <Link
                        href={route(paginator.current_page - 1)}
                        preserveScroll
                        preserveState
                    >
                        Anterior
                    </Link>
                </Button>
            ) : (
                <Button size="sm" variant="outline" disabled>
                    Anterior
                </Button>
            )}
            <span className="text-xs text-muted-foreground">
                Página {paginator.current_page} de {paginator.last_page}
            </span>
            {paginator.next_page_url ? (
                <Button asChild size="sm" variant="outline">
                    <Link
                        href={route(paginator.current_page + 1)}
                        preserveScroll
                        preserveState
                    >
                        Siguiente
                    </Link>
                </Button>
            ) : (
                <Button size="sm" variant="outline" disabled>
                    Siguiente
                </Button>
            )}
        </nav>
    );
}

function DecisionControls({
    budgetId,
    file,
    issue,
}: {
    budgetId: number;
    file: OwnRevenueImportFile;
    issue: OwnRevenueWorkSheetIssue;
}) {
    const form = useForm({
        analysis_revision: file.analysis_revision ?? '',
        decision: issue.decision?.status ?? '',
        justification: issue.decision?.justification ?? '',
    });
    const feedback = workSheetDecisionFeedback(form.errors);

    const submit = (decision: 'accepted' | 'rejected'): void => {
        form.transform((data) => ({ ...data, decision }));
        form.submit(
            OwnRevenueImportDecisionController({
                budget: budgetId,
                importFile: file.id,
                issue: issue.id,
            }),
            { preserveScroll: true },
        );
    };

    return (
        <div className="grid gap-3 border-t pt-3">
            <div className="grid gap-2">
                <Label htmlFor={`justification-${issue.id}`}>
                    Nota de la decisión (opcional)
                </Label>
                <textarea
                    id={`justification-${issue.id}`}
                    value={form.data.justification}
                    onChange={(event) =>
                        form.setData('justification', event.target.value)
                    }
                    placeholder="Agrega el motivo si ayuda a documentar la revisión."
                    disabled={form.processing}
                    maxLength={2000}
                    rows={3}
                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                />
            </div>
            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    size="sm"
                    onClick={() => submit('accepted')}
                    disabled={form.processing}
                    aria-label={`Aceptar diferencia de la partida ${issue.item_code ?? ''}`}
                >
                    {form.processing ? (
                        <LoaderCircle className="size-4 animate-spin" />
                    ) : (
                        <Check className="size-4" />
                    )}
                    Aceptar diferencia
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => submit('rejected')}
                    disabled={form.processing}
                    aria-label={`No aceptar diferencia de la partida ${issue.item_code ?? ''}`}
                >
                    <X className="size-4" />
                    No aceptar
                </Button>
            </div>
            {feedback && (
                <p className="text-sm text-destructive" role="alert">
                    {feedback}
                </p>
            )}
        </div>
    );
}

export default function WorkSheetPreview({
    budgetId,
    file,
    preview,
    blockingIssues,
    reviewIssues,
    viewState,
    decisionsEnabled,
    canConfirm,
    confirmReasons,
    permissions,
}: Props) {
    const stateMessage = previewStateMessage(viewState);
    const confirmationForm = useForm({
        analysis_revision: file.analysis_revision ?? '',
    });
    const showConfirmControl = canConfirmWorkSheet({
        canManage: permissions.manage,
        canConfirm,
        analysisRevision: file.analysis_revision,
    });
    const confirmationFeedback = workSheetConfirmationFeedback(
        confirmationForm.errors,
    );

    const confirmWorkSheet = (): void => {
        if (!showConfirmControl) {
            return;
        }

        if (
            !window.confirm(
                'Confirmar esta Hoja de trabajo guardará su calendarización y reemplazará la versión confirmada anterior. El ABPRE conservará el importe oficial. ¿Deseas continuar?',
            )
        ) {
            return;
        }

        confirmationForm.submit(
            OwnRevenueWorkSheetConfirmationController({
                budget: budgetId,
                importFile: file.id,
            }),
            { preserveScroll: true },
        );
    };

    return (
        <div className="grid gap-5">
            {viewState !== 'ready' && stateMessage && (
                <div
                    className="rounded-lg border bg-muted/40 p-4 text-sm text-muted-foreground"
                    role="status"
                >
                    {stateMessage}
                </div>
            )}
            {file.status !== 'confirmed' && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Confirmación de la Hoja de trabajo
                        </CardTitle>
                        <CardDescription>
                            La calendarización se guardará como una versión
                            independiente. El ABPRE seguirá siendo la fuente del
                            importe oficial.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {confirmReasons.length > 0 && (
                            <ul className="grid list-disc gap-1 pl-5 text-sm text-muted-foreground">
                                {confirmReasons.map((reason) => (
                                    <li key={reason}>{reason}</li>
                                ))}
                            </ul>
                        )}
                        {showConfirmControl && (
                            <Button
                                type="button"
                                className="w-fit"
                                onClick={confirmWorkSheet}
                                disabled={confirmationForm.processing}
                            >
                                {confirmationForm.processing ? (
                                    <LoaderCircle className="size-4 animate-spin" />
                                ) : (
                                    <Check className="size-4" />
                                )}
                                Confirmar Hoja de trabajo
                            </Button>
                        )}
                        <InputError message={confirmationFeedback} />
                    </CardContent>
                </Card>
            )}
            <section className="grid gap-3 sm:grid-cols-3" aria-label="Resumen">
                <Card>
                    <CardHeader className="gap-1 pb-3">
                        <CardDescription>Renglones revisados</CardDescription>
                        <CardTitle className="text-2xl">
                            {preview.total}
                        </CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader className="gap-1 pb-3">
                        <CardDescription>
                            Problemas por corregir
                        </CardDescription>
                        <CardTitle className="text-2xl text-destructive">
                            {blockingIssues.total}
                        </CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader className="gap-1 pb-3">
                        <CardDescription>Avisos por revisar</CardDescription>
                        <CardTitle className="text-2xl text-amber-700 dark:text-amber-300">
                            {reviewIssues.total}
                        </CardTitle>
                    </CardHeader>
                </Card>
            </section>

            {blockingIssues.total > 0 && (
                <Card className="border-destructive/50">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <AlertCircle className="size-5 text-destructive" />
                            Problemas que impiden continuar
                        </CardTitle>
                        <CardDescription>
                            Corrige estos datos en el archivo y vuelve a
                            analizarlo.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {blockingIssues.data.map((issue) => (
                            <p
                                key={issue.id}
                                className="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm"
                            >
                                {issue.message}
                            </p>
                        ))}
                        <Pagination
                            paginator={blockingIssues}
                            pageName="blocking_page"
                            label="Páginas de problemas por corregir"
                            budgetId={budgetId}
                            fileId={file.id}
                        />
                    </CardContent>
                </Card>
            )}

            {reviewIssues.total > 0 && (
                <Card className="border-amber-300 dark:border-amber-800">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <CircleAlert className="size-5 text-amber-700 dark:text-amber-300" />
                            Avisos por revisar
                        </CardTitle>
                        <CardDescription>
                            Revisa estas observaciones antes de continuar. El
                            ABPRE conserva el importe oficial.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {reviewIssues.data.map((issue) => (
                            <article
                                key={issue.id}
                                className="grid gap-3 rounded-lg border bg-amber-50/60 p-4 dark:bg-amber-950/20"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div className="grid gap-1">
                                        <p className="text-sm font-medium">
                                            {issue.message}
                                        </p>
                                        {issue.item_code && (
                                            <p className="text-xs text-muted-foreground">
                                                Partida {issue.item_code}
                                            </p>
                                        )}
                                    </div>
                                    {issue.decision && (
                                        <Badge
                                            variant="outline"
                                            className={
                                                issue.decision.status ===
                                                'accepted'
                                                    ? 'border-emerald-300 text-emerald-700 dark:border-emerald-800 dark:text-emerald-300'
                                                    : 'border-slate-300 text-slate-700 dark:border-slate-700 dark:text-slate-300'
                                            }
                                        >
                                            {issue.decision.status ===
                                            'accepted'
                                                ? 'Diferencia aceptada'
                                                : 'Diferencia no aceptada'}
                                        </Badge>
                                    )}
                                </div>
                                {issue.work_sheet_amount_cents !== null &&
                                    issue.abpre_amount_cents !== null &&
                                    issue.difference_cents !== null && (
                                        <dl className="grid gap-3 text-sm sm:grid-cols-3">
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Hoja de trabajo
                                                </dt>
                                                <dd className="font-medium tabular-nums">
                                                    {formatCents(
                                                        issue.work_sheet_amount_cents,
                                                    )}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    ABPRE
                                                </dt>
                                                <dd className="font-medium tabular-nums">
                                                    {formatCents(
                                                        issue.abpre_amount_cents,
                                                    )}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    Diferencia
                                                </dt>
                                                <dd className="font-medium tabular-nums">
                                                    {formatCents(
                                                        issue.difference_cents,
                                                    )}
                                                </dd>
                                            </div>
                                        </dl>
                                    )}
                                {issue.requires_decision && (
                                    <p className="text-sm text-muted-foreground">
                                        Esta diferencia requiere indicar si
                                        puede conservarse la calendarización de
                                        la Hoja de trabajo.
                                    </p>
                                )}
                                {canManageWorkSheetDecision({
                                    canManage: permissions.manage,
                                    decisionsEnabled,
                                    requiresDecision: issue.requires_decision,
                                }) &&
                                    file.analysis_revision && (
                                        <DecisionControls
                                            budgetId={budgetId}
                                            file={file}
                                            issue={issue}
                                        />
                                    )}
                            </article>
                        ))}
                        <Pagination
                            paginator={reviewIssues}
                            pageName="review_page"
                            label="Páginas de avisos por revisar"
                            budgetId={budgetId}
                            fileId={file.id}
                        />
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>
                        Calendarización por actividad y partida
                    </CardTitle>
                    <CardDescription>
                        “Anual” corresponde a la actividad de este renglón. El
                        importe ABPRE y la diferencia son totales de toda la
                        partida y pueden repetirse cuando una partida aparece en
                        varias actividades.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4">
                    {preview.data.length === 0 ? (
                        <p className="rounded-lg bg-muted/50 p-4 text-sm text-muted-foreground">
                            {stateMessage ||
                                'No hay renglones disponibles para esta vista.'}
                        </p>
                    ) : (
                        <div className="max-h-[65vh] overflow-auto rounded-lg border">
                            <table className="w-full min-w-[150rem] text-xs">
                                <caption className="sr-only">
                                    Calendarización mensual por actividad e
                                    insumo, con comparación total por partida
                                    contra el ABPRE.
                                </caption>
                                <thead className="sticky top-0 z-10 bg-muted text-left text-muted-foreground shadow-sm">
                                    <tr>
                                        <th scope="col" className="px-3 py-2">
                                            Actividad
                                        </th>
                                        <th scope="col" className="px-3 py-2">
                                            Insumo
                                        </th>
                                        <th scope="col" className="px-3 py-2">
                                            Partida
                                        </th>
                                        <th scope="col" className="px-3 py-2">
                                            Regiones de origen
                                        </th>
                                        {months.map(([, label]) => (
                                            <th
                                                key={label}
                                                scope="col"
                                                className="px-3 py-2 text-right"
                                            >
                                                {label}
                                            </th>
                                        ))}
                                        <th
                                            scope="col"
                                            className="px-3 py-2 text-right"
                                        >
                                            Anual
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-3 py-2 text-right"
                                        >
                                            Total ABPRE de la partida
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-3 py-2 text-right"
                                        >
                                            Diferencia total de la partida
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {preview.data.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="border-t align-top"
                                        >
                                            <td className="px-3 py-2">
                                                <span className="font-medium">
                                                    {row.activityCode}
                                                </span>
                                                <span className="mt-0.5 block max-w-64 text-muted-foreground">
                                                    {row.activityName}
                                                </span>
                                            </td>
                                            <td className="max-w-72 px-3 py-2">
                                                {row.itemName}
                                            </td>
                                            <td className="px-3 py-2 font-medium">
                                                {row.specificItemCode}
                                            </td>
                                            <td className="max-w-72 px-3 py-2">
                                                {row.sourceRegions.length
                                                    ? row.sourceRegions
                                                          .map(
                                                              (region) =>
                                                                  `${region.code} · ${region.name}`,
                                                          )
                                                          .join(', ')
                                                    : '—'}
                                            </td>
                                            {months.map(([key, label]) => (
                                                <td
                                                    key={label}
                                                    className="px-3 py-2 text-right tabular-nums"
                                                >
                                                    {formatCents(
                                                        row.months[key] ?? '0',
                                                    )}
                                                </td>
                                            ))}
                                            <td className="px-3 py-2 text-right font-semibold tabular-nums">
                                                {formatCents(
                                                    row.annualAmountCents,
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-right font-semibold tabular-nums">
                                                {formatCents(
                                                    row.abpreAmountCents,
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-right font-semibold tabular-nums">
                                                {formatCents(
                                                    row.differenceCents,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <Pagination
                        paginator={preview}
                        pageName="preview_page"
                        label="Páginas de renglones de la Hoja de trabajo"
                        budgetId={budgetId}
                        fileId={file.id}
                    />
                </CardContent>
            </Card>
        </div>
    );
}
