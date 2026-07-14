import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Download, FileQuestion, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import OwnRevenueImportFileController from '@/actions/App/Http/Controllers/Finance/OwnRevenueImportFileController';
import { formatCents } from '@/components/finance/own-revenue/imports/abpre-preview';
import ImportFileSlot from '@/components/finance/own-revenue/imports/import-file-slot';
import {
    failImportMutation,
    finishImportMutation,
    importFilePresentation,
    initialImportMutation,
    startImportMutation,
} from '@/components/finance/own-revenue/imports/import-workspace-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { show as showBudget } from '@/routes/finance/own-revenue/budgets';
import { show as showImports } from '@/routes/finance/own-revenue/budgets/imports';
import type {
    OwnRevenueImportFormat,
    OwnRevenueImportWorkspaceProps,
} from '@/types/finance-own-revenue-imports';

function queryFor(
    currentUrl: string,
    name: string,
    page: number,
): Record<string, string> {
    const query = new URLSearchParams(currentUrl.split('?')[1] ?? '');
    query.set(name, String(page));

    return Object.fromEntries(query.entries());
}

export default function OwnRevenueImportShow({
    budget,
    slots,
    unassigned_files: unassignedFiles,
    unassigned_files_meta: unassignedMeta,
    selected_file: selectedFile,
    permissions,
}: OwnRevenueImportWorkspaceProps) {
    const currentUrl = usePage().url;
    const readyCount = slots.filter(
        (slot) => slot.has_confirmed || slot.latest_status === 'ready',
    ).length;
    const parserPendingCount = slots.filter(
        (slot) => slot.format !== 'work_sheet' && slot.has_parser_pending,
    ).length;
    const missingCount = slots.filter((slot) => slot.is_missing).length;
    const [mutationFeedback, setMutationFeedback] = useState(
        initialImportMutation,
    );

    const assignUnclassified = (
        fileId: number,
        format: OwnRevenueImportFormat,
    ): void => {
        setMutationFeedback((current) => startImportMutation(current, fileId));
        router.put(
            OwnRevenueImportFileController.updateFormat({
                budget: budget.id,
                importFile: fileId,
            }),
            { format },
            {
                preserveScroll: true,
                onError: (errors) =>
                    setMutationFeedback((current) =>
                        failImportMutation(current, errors),
                    ),
                onFinish: () =>
                    setMutationFeedback((current) =>
                        finishImportMutation(current),
                    ),
            },
        );
    };

    return (
        <>
            <Head title={`Importar archivos ${budget.fiscal_year}`} />
            <main className="flex h-full flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="grid gap-3">
                    <Button asChild variant="ghost" size="sm" className="w-fit">
                        <Link href={showBudget(budget.id)}>
                            <ArrowLeft className="size-4" />
                            Volver al presupuesto
                        </Link>
                    </Button>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Presupuesto de Ingresos Propios
                            </p>
                            <h1 className="mt-1 text-2xl font-semibold">
                                Importar archivos del presupuesto
                            </h1>
                            <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                                Agrega y revisa los formatos correspondientes a{' '}
                                {budget.fiscal_year}.
                            </p>
                        </div>
                        <Badge variant="outline" className="w-fit">
                            <ShieldCheck className="size-3" />
                            {permissions.manage
                                ? 'Gestión habilitada'
                                : 'Sólo consulta'}
                        </Badge>
                    </div>
                </header>

                <section
                    className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                    aria-label="Resumen de importaciones"
                >
                    <Summary label="Listos" value={readyCount} tone="success" />
                    <Summary
                        label="Faltantes"
                        value={missingCount}
                        tone="warning"
                    />
                    <Summary
                        label="Revisión no disponible"
                        value={parserPendingCount}
                    />
                    <Summary
                        label="Por clasificar"
                        value={unassignedMeta.total}
                    />
                </section>

                <details className="group rounded-lg border bg-card text-card-foreground shadow-sm">
                    <summary className="cursor-pointer list-none px-6 py-4 font-medium marker:hidden">
                        Más información
                    </summary>
                    <div className="grid gap-3 border-t px-6 py-4 text-sm sm:grid-cols-2 xl:grid-cols-4">
                        <InstitutionValue
                            label="Institución"
                            value={budget.institution_name}
                        />
                        <InstitutionValue
                            label="Unidad responsable"
                            value={`${budget.responsible_unit_code} · ${budget.responsible_unit_name}`}
                        />
                        <InstitutionValue
                            label="Programa"
                            value={`${budget.budget_program_code} · ${budget.budget_program_name}`}
                        />
                        <InstitutionValue
                            label="Región"
                            value={`${budget.region_code} · ${budget.region_name}`}
                        />
                        <InstitutionValue
                            label="Ingreso estimado"
                            value={
                                budget.estimated_income_cents === null
                                    ? 'Pendiente'
                                    : formatCents(budget.estimated_income_cents)
                            }
                        />
                    </div>
                </details>

                <section
                    className="grid gap-4 xl:grid-cols-2"
                    aria-label="Cinco formatos requeridos"
                >
                    {slots.map((slot) => (
                        <ImportFileSlot
                            key={slot.format}
                            budgetId={budget.id}
                            slot={slot}
                            selectedFile={selectedFile}
                            permissions={permissions}
                        />
                    ))}
                </section>

                {unassignedFiles.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileQuestion className="size-5" />
                                Archivos sin clasificar
                            </CardTitle>
                            <CardDescription>
                                La detección no pudo asignarlos con suficiente
                                confianza.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-2">
                            {mutationFeedback.error && (
                                <p
                                    role="alert"
                                    aria-live="assertive"
                                    className="rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive"
                                >
                                    {mutationFeedback.error}
                                </p>
                            )}
                            {unassignedFiles.map((file) => (
                                <div
                                    key={file.id}
                                    className="flex flex-col gap-2 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium">
                                            {file.name}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {file.size.toLocaleString('es-MX')}{' '}
                                            bytes ·{' '}
                                            {
                                                importFilePresentation({
                                                    status: file.status,
                                                    format: file.format,
                                                    analyzed: file.analyzed,
                                                    issueCount:
                                                        file.issue_counts
                                                            .error +
                                                        file.issue_counts
                                                            .warning +
                                                        file.issue_counts.info,
                                                    canReclassify:
                                                        file.can_reclassify,
                                                }).label
                                            }
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {permissions.download && (
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="ghost"
                                            >
                                                <a
                                                    href={
                                                        OwnRevenueImportFileController.download(
                                                            {
                                                                budget: budget.id,
                                                                importFile:
                                                                    file.id,
                                                            },
                                                        ).url
                                                    }
                                                >
                                                    <Download className="size-3" />{' '}
                                                    Descargar
                                                </a>
                                            </Button>
                                        )}
                                        {permissions.manage &&
                                            file.can_reclassify && (
                                                <select
                                                    aria-label={`Asignar tipo de ${file.name}`}
                                                    defaultValue=""
                                                    disabled={
                                                        mutationFeedback.activeFileId !==
                                                        null
                                                    }
                                                    onChange={(event) =>
                                                        assignUnclassified(
                                                            file.id,
                                                            event.target
                                                                .value as OwnRevenueImportFormat,
                                                        )
                                                    }
                                                    className="h-8 rounded-md border border-input bg-background px-2 text-xs"
                                                >
                                                    <option value="" disabled>
                                                        Asignar formato
                                                    </option>
                                                    {slots.map((slot) => (
                                                        <option
                                                            key={slot.format}
                                                            value={slot.format}
                                                        >
                                                            {slot.label}
                                                        </option>
                                                    ))}
                                                </select>
                                            )}
                                    </div>
                                </div>
                            ))}
                            {unassignedMeta.last_page > 1 && (
                                <nav
                                    className="flex items-center justify-between gap-2"
                                    aria-label="Páginas de archivos sin clasificar"
                                >
                                    {unassignedMeta.current_page > 1 ? (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={showImports(budget.id, {
                                                    query: queryFor(
                                                        currentUrl,
                                                        'unassigned_page',
                                                        unassignedMeta.current_page -
                                                            1,
                                                    ),
                                                })}
                                                preserveScroll
                                            >
                                                Anterior
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            disabled
                                        >
                                            Anterior
                                        </Button>
                                    )}
                                    <span className="text-xs text-muted-foreground">
                                        Página {unassignedMeta.current_page} de{' '}
                                        {unassignedMeta.last_page}
                                    </span>
                                    {unassignedMeta.has_more ? (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={showImports(budget.id, {
                                                    query: queryFor(
                                                        currentUrl,
                                                        'unassigned_page',
                                                        unassignedMeta.current_page +
                                                            1,
                                                    ),
                                                })}
                                                preserveScroll
                                            >
                                                Siguiente
                                            </Link>
                                        </Button>
                                    ) : (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            disabled
                                        >
                                            Siguiente
                                        </Button>
                                    )}
                                </nav>
                            )}
                        </CardContent>
                    </Card>
                )}
            </main>
        </>
    );
}

function Summary({
    label,
    value,
    tone = 'neutral',
}: {
    label: string;
    value: number;
    tone?: 'neutral' | 'success' | 'warning';
}) {
    const toneClass =
        tone === 'success'
            ? 'border-emerald-300 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/30'
            : tone === 'warning'
              ? 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30'
              : 'bg-card';

    return (
        <div className={`rounded-lg border p-4 ${toneClass}`}>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-2xl font-semibold">{value}</p>
        </div>
    );
}

function InstitutionValue({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 font-medium">{value}</p>
        </div>
    );
}
