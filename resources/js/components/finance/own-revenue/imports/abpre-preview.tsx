import { Link, useForm, usePage, useRemember } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import OwnRevenueAbpreConfirmationController from '@/actions/App/Http/Controllers/Finance/OwnRevenueAbpreConfirmationController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show } from '@/routes/finance/own-revenue/budgets/imports';
import type {
    LengthAwarePaginator,
    OwnRevenueAbprePreviewRow,
    OwnRevenueImportDecision,
    OwnRevenueImportIssue,
    OwnRevenueImportPermissions,
    OwnRevenueImportFileStatus,
    OwnRevenueSelectedImportFile,
} from '@/types/finance-own-revenue-imports';

type Props = {
    budgetId: number;
    preview: LengthAwarePaginator<OwnRevenueAbprePreviewRow>;
    previewFile: {
        id: number;
        name: string;
        version: number;
        status: OwnRevenueImportFileStatus;
    } | null;
    decisionWarnings: LengthAwarePaginator<OwnRevenueImportIssue> & {
        has_more: boolean;
    };
    selectedFile: OwnRevenueSelectedImportFile | null;
    permissions: OwnRevenueImportPermissions;
};

const months = [
    ['1', 'Ene'],
    ['2', 'Feb'],
    ['3', 'Mar'],
    ['4', 'Abr'],
    ['5', 'May'],
    ['6', 'Jun'],
    ['7', 'Jul'],
    ['8', 'Ago'],
    ['9', 'Sep'],
    ['10', 'Oct'],
    ['11', 'Nov'],
    ['12', 'Dic'],
] as const;

export function formatCents(rawCents: string): string {
    const negative = rawCents.startsWith('-');
    const digits = (negative ? rawCents.slice(1) : rawCents)
        .replace(/\D/g, '')
        .replace(/^0+(?=\d)/, '')
        .padStart(3, '0');
    const whole = digits.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const fraction = digits.slice(-2);

    return `${negative ? '-' : ''}$${whole}.${fraction}`;
}

function queryFor(
    currentUrl: string,
    name: 'preview_page' | 'decisions_page',
    page: number,
): Record<string, string> {
    const query = new URLSearchParams(currentUrl.split('?')[1] ?? '');
    query.set(name, String(page));

    return Object.fromEntries(query.entries());
}

export default function AbprePreview({
    budgetId,
    preview,
    previewFile,
    decisionWarnings,
    selectedFile,
    permissions,
}: Props) {
    const currentUrl = usePage().url;
    const [decisions, setDecisions] = useRemember<OwnRevenueImportDecision[]>(
        [],
        `own-revenue-abpre-decisions-${previewFile?.id ?? 'none'}`,
    );
    const form = useForm<{ decisions: OwnRevenueImportDecision[] }>({
        decisions: [],
    });
    const canConfirm =
        permissions.confirm &&
        previewFile?.status === 'ready' &&
        selectedFile?.id === previewFile.id;
    const resolvedDecisionCount = decisions.length;
    const decisionsComplete = resolvedDecisionCount === decisionWarnings.total;

    const chooseDecision = (
        issueId: number,
        resolution: OwnRevenueImportDecision['resolution'],
    ): void => {
        setDecisions((currentDecisions) => {
            const existing = currentDecisions.find(
                (decision) => decision.issue_id === issueId,
            );

            if (existing) {
                return currentDecisions.map((decision) =>
                    decision.issue_id === issueId
                        ? { ...decision, resolution }
                        : decision,
                );
            }

            return [
                ...currentDecisions,
                {
                    issue_id: issueId,
                    resolution,
                    resolved_value: null,
                    justification: null,
                },
            ];
        });
    };

    const updateDecision = (
        issueId: number,
        values: Partial<OwnRevenueImportDecision>,
    ): void => {
        setDecisions((currentDecisions) =>
            currentDecisions.map((decision) =>
                decision.issue_id === issueId
                    ? { ...decision, ...values }
                    : decision,
            ),
        );
    };

    const confirmImport = (): void => {
        if (!previewFile || !canConfirm || !decisionsComplete) {
            return;
        }

        if (
            !window.confirm(
                'Confirmar este ABPRE hará sus líneas inmutables y reemplazará la versión confirmada anterior. ¿Deseas continuar?',
            )
        ) {
            return;
        }

        form.transform(() => ({ decisions }));
        form.submit(
            OwnRevenueAbpreConfirmationController({
                budget: budgetId,
                importFile: previewFile.id,
            }),
            { preserveScroll: true },
        );
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Vista previa ABPRE</CardTitle>
                <CardDescription>
                    {previewFile
                        ? `${previewFile.name} · versión ${previewFile.version}. `
                        : ''}
                    Importes exactos del análisis del servidor. Se muestran{' '}
                    {preview.total} líneas.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                {preview.data.length === 0 ? (
                    <p className="rounded-lg bg-muted/50 p-4 text-sm text-muted-foreground">
                        Analiza una versión ABPRE para ver sus líneas
                        normalizadas.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border">
                        <table className="w-full min-w-7xl text-xs">
                            <thead className="bg-muted/60 text-left text-muted-foreground">
                                <tr>
                                    <th scope="col" className="px-3 py-2">
                                        Partida
                                    </th>
                                    <th scope="col" className="px-3 py-2">
                                        Actividad
                                    </th>
                                    <th scope="col" className="px-3 py-2">
                                        Región original
                                    </th>
                                    <th scope="col" className="px-3 py-2">
                                        Región normalizada
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
                                </tr>
                            </thead>
                            <tbody>
                                {preview.data.map((row) => (
                                    <tr key={row.id} className="border-t">
                                        <td className="px-3 py-2 font-medium">
                                            {row.specificItemCode ?? '—'}
                                        </td>
                                        <td className="px-3 py-2">
                                            {row.officialActivityCode ?? '—'}
                                        </td>
                                        <td className="px-3 py-2">
                                            {row.sourceRegions?.length
                                                ? row.sourceRegions
                                                      .map(
                                                          (region) =>
                                                              `${region.code} · ${region.name}`,
                                                      )
                                                      .join(', ')
                                                : '—'}
                                        </td>
                                        <td className="px-3 py-2">
                                            {row.regionCode && row.regionName
                                                ? `${row.regionCode} · ${row.regionName}`
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
                                            {formatCents(row.annualAmountCents)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {preview.last_page > 1 && (
                    <nav
                        className="flex items-center justify-between gap-2"
                        aria-label="Páginas de vista previa ABPRE"
                    >
                        {preview.current_page > 1 ? (
                            <Button asChild size="sm" variant="outline">
                                <Link
                                    href={show(budgetId, {
                                        query: queryFor(
                                            currentUrl,
                                            'preview_page',
                                            preview.current_page - 1,
                                        ),
                                    })}
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
                            Página {preview.current_page} de {preview.last_page}
                        </span>
                        {preview.next_page_url ? (
                            <Button asChild size="sm" variant="outline">
                                <Link
                                    href={show(budgetId, {
                                        query: queryFor(
                                            currentUrl,
                                            'preview_page',
                                            preview.current_page + 1,
                                        ),
                                    })}
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
                )}

                {canConfirm && decisionWarnings.total > 0 && (
                    <fieldset className="grid gap-3 rounded-lg border border-amber-300 bg-amber-50/70 p-4 dark:border-amber-800 dark:bg-amber-950/30">
                        <legend className="px-1 text-sm font-semibold">
                            Decisiones requeridas
                        </legend>
                        <p
                            className="text-sm text-muted-foreground"
                            aria-live="polite"
                        >
                            {resolvedDecisionCount} de {decisionWarnings.total}{' '}
                            advertencias resueltas. Cada decisión requiere una
                            selección explícita.
                        </p>
                        {decisionWarnings.data.map((issue) => {
                            const decision = decisions.find(
                                (item) => item.issue_id === issue.id,
                            );

                            return (
                                <div
                                    key={issue.id}
                                    className="grid gap-3 rounded-md border bg-background p-3"
                                >
                                    <div>
                                        <p className="text-sm font-medium">
                                            {issue.message}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {issue.code}
                                        </p>
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-3">
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`resolution-${issue.id}`}
                                            >
                                                Resolución
                                            </Label>
                                            <select
                                                id={`resolution-${issue.id}`}
                                                value={
                                                    decision?.resolution ?? ''
                                                }
                                                onChange={(event) =>
                                                    chooseDecision(
                                                        issue.id,
                                                        event.target
                                                            .value as OwnRevenueImportDecision['resolution'],
                                                    )
                                                }
                                                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                            >
                                                <option value="" disabled>
                                                    Selecciona una resolución
                                                </option>
                                                <option value="manual">
                                                    Captura manual
                                                </option>
                                                <option value="xlsx">
                                                    Conservar XLSX
                                                </option>
                                                <option value="custom">
                                                    Valor personalizado
                                                </option>
                                            </select>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`resolved-${issue.id}`}
                                            >
                                                Valor resuelto (opcional)
                                            </Label>
                                            <Input
                                                id={`resolved-${issue.id}`}
                                                value={String(
                                                    decision?.resolved_value ??
                                                        '',
                                                )}
                                                onChange={(event) =>
                                                    updateDecision(issue.id, {
                                                        resolved_value:
                                                            event.target
                                                                .value || null,
                                                    })
                                                }
                                                disabled={!decision}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor={`justification-${issue.id}`}
                                            >
                                                Justificación (opcional)
                                            </Label>
                                            <Input
                                                id={`justification-${issue.id}`}
                                                value={
                                                    decision?.justification ??
                                                    ''
                                                }
                                                onChange={(event) =>
                                                    updateDecision(issue.id, {
                                                        justification:
                                                            event.target
                                                                .value || null,
                                                    })
                                                }
                                                disabled={!decision}
                                            />
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                        {decisionWarnings.last_page > 1 && (
                            <nav
                                className="flex items-center justify-between gap-2"
                                aria-label="Páginas de decisiones requeridas"
                            >
                                {decisionWarnings.current_page > 1 ? (
                                    <Button asChild size="sm" variant="outline">
                                        <Link
                                            href={show(budgetId, {
                                                query: queryFor(
                                                    currentUrl,
                                                    'decisions_page',
                                                    decisionWarnings.current_page -
                                                        1,
                                                ),
                                            })}
                                            preserveScroll
                                            preserveState
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
                                    Página {decisionWarnings.current_page} de{' '}
                                    {decisionWarnings.last_page}
                                </span>
                                {decisionWarnings.has_more ? (
                                    <Button asChild size="sm" variant="outline">
                                        <Link
                                            href={show(budgetId, {
                                                query: queryFor(
                                                    currentUrl,
                                                    'decisions_page',
                                                    decisionWarnings.current_page +
                                                        1,
                                                ),
                                            })}
                                            preserveScroll
                                            preserveState
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
                    </fieldset>
                )}

                <InputError message={form.errors.decisions} />
                {canConfirm && (
                    <div className="flex justify-end">
                        <Button
                            type="button"
                            onClick={confirmImport}
                            disabled={form.processing || !decisionsComplete}
                        >
                            <CheckCircle2 className="size-4" />
                            {form.processing
                                ? 'Confirmando…'
                                : 'Confirmar ABPRE listo'}
                        </Button>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
