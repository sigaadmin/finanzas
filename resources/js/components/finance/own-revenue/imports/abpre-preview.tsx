import { Link, useForm, usePage, useRemember } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { useEffect, useMemo } from 'react';
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
    OwnRevenueImportPermissions,
    OwnRevenueSelectedImportFile,
} from '@/types/finance-own-revenue-imports';

type Props = {
    budgetId: number;
    preview: LengthAwarePaginator<OwnRevenueAbprePreviewRow>;
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

const requiredDecisionCodes = new Set([
    'year.mismatch',
    'region.normalized',
    'abpre.annual_mismatch',
    'abpre.missing_justification',
]);

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

function queryFor(currentUrl: string, page: number): Record<string, string> {
    const query = new URLSearchParams(currentUrl.split('?')[1] ?? '');
    query.set('preview_page', String(page));

    return Object.fromEntries(query.entries());
}

export default function AbprePreview({
    budgetId,
    preview,
    selectedFile,
    permissions,
}: Props) {
    const currentUrl = usePage().url;
    const requiredIssues = useMemo(
        () =>
            selectedFile?.issues.data.filter(
                (issue) =>
                    issue.severity === 'warning' &&
                    requiredDecisionCodes.has(issue.code),
            ) ?? [],
        [selectedFile],
    );
    const [decisions, setDecisions] = useRemember<OwnRevenueImportDecision[]>(
        [],
        `own-revenue-abpre-decisions-${selectedFile?.id ?? 'none'}`,
    );
    const form = useForm<{ decisions: OwnRevenueImportDecision[] }>({
        decisions: [],
    });
    const canConfirm =
        permissions.confirm &&
        selectedFile?.format === 'abpre' &&
        selectedFile.status === 'ready';

    useEffect(() => {
        setDecisions((currentDecisions) => {
            const missingIssues = requiredIssues.filter(
                (issue) =>
                    !currentDecisions.some(
                        (decision) => decision.issue_id === issue.id,
                    ),
            );

            if (missingIssues.length === 0) {
                return currentDecisions;
            }

            return [
                ...currentDecisions,
                ...missingIssues.map((issue) => ({
                    issue_id: issue.id,
                    resolution: 'manual' as const,
                    resolved_value: null,
                    justification: null,
                })),
            ];
        });
    }, [requiredIssues, setDecisions]);

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
        if (!selectedFile || !canConfirm) {
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
                importFile: selectedFile.id,
            }),
            { preserveScroll: true },
        );
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Vista previa ABPRE</CardTitle>
                <CardDescription>
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
                                            {row.regionCode ?? '—'}
                                        </td>
                                        <td className="px-3 py-2">
                                            {row.regionName ?? '—'}
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
                        <Button
                            asChild
                            size="sm"
                            variant="outline"
                            disabled={preview.current_page === 1}
                        >
                            <Link
                                href={show(budgetId, {
                                    query: queryFor(
                                        currentUrl,
                                        Math.max(1, preview.current_page - 1),
                                    ),
                                })}
                                preserveScroll
                            >
                                Anterior
                            </Link>
                        </Button>
                        <span className="text-xs text-muted-foreground">
                            Página {preview.current_page} de {preview.last_page}
                        </span>
                        <Button
                            asChild
                            size="sm"
                            variant="outline"
                            disabled={!preview.next_page_url}
                        >
                            <Link
                                href={show(budgetId, {
                                    query: queryFor(
                                        currentUrl,
                                        preview.current_page + 1,
                                    ),
                                })}
                                preserveScroll
                            >
                                Siguiente
                            </Link>
                        </Button>
                    </nav>
                )}

                {canConfirm && requiredIssues.length > 0 && (
                    <fieldset className="grid gap-3 rounded-lg border border-amber-300 bg-amber-50/70 p-4 dark:border-amber-800 dark:bg-amber-950/30">
                        <legend className="px-1 text-sm font-semibold">
                            Decisiones requeridas
                        </legend>
                        {requiredIssues.map((issue) => {
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
                                                    decision?.resolution ??
                                                    'manual'
                                                }
                                                onChange={(event) =>
                                                    updateDecision(issue.id, {
                                                        resolution: event.target
                                                            .value as OwnRevenueImportDecision['resolution'],
                                                    })
                                                }
                                                className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                            >
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
                                            />
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </fieldset>
                )}

                <InputError message={form.errors.decisions} />
                {canConfirm && (
                    <div className="flex justify-end">
                        <Button
                            type="button"
                            onClick={confirmImport}
                            disabled={form.processing}
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
