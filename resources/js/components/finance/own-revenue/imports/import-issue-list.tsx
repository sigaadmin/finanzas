import { Link, router, usePage } from '@inertiajs/react';
import { AlertCircle, Info, ListChecks, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import {
    importIssueDialogOpenAction,
    importIssueDialogState,
    importIssuePageQuery,
} from '@/components/finance/own-revenue/imports/import-workspace-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { show } from '@/routes/finance/own-revenue/budgets/imports';
import type {
    OwnRevenueImportFile,
    OwnRevenueImportIssue,
    OwnRevenueSelectedImportFile,
} from '@/types/finance-own-revenue-imports';

type Props = {
    budgetId: number;
    file: OwnRevenueImportFile;
    selectedFile: OwnRevenueSelectedImportFile | null;
};

const contextLabels: Record<string, string> = {
    detected_year: 'Año detectado',
    fiscal_year: 'Año fiscal',
    responsible_unit_code: 'Unidad responsable',
    specific_item_code: 'Partida específica',
    source_region: 'Región original',
    normalized_region: 'Región normalizada',
    value: 'Valor',
    source_cents: 'Centavos del archivo',
    calculated_cents: 'Centavos calculados',
};

const severityLabels = {
    error: 'Errores',
    warning: 'Advertencias',
    info: 'Avisos',
};

function IssueIcon({ issue }: { issue: OwnRevenueImportIssue }) {
    if (issue.severity === 'error') {
        return <AlertCircle className="mt-0.5 size-4 text-destructive" />;
    }

    if (issue.severity === 'warning') {
        return (
            <TriangleAlert className="mt-0.5 size-4 text-amber-600 dark:text-amber-300" />
        );
    }

    return <Info className="mt-0.5 size-4 text-blue-600 dark:text-blue-300" />;
}

export default function ImportIssueList({
    budgetId,
    file,
    selectedFile,
}: Props) {
    const currentUrl = usePage().url;
    const [dialogState, setDialogState] = useState(() =>
        importIssueDialogState(undefined, false),
    );
    const issues = selectedFile?.id === file.id ? selectedFile.issues : null;

    const setDialogOpen = (isOpen: boolean): void => {
        setDialogState((current) => importIssueDialogState(current, isOpen));
    };

    const openIssues = (): void => {
        const action = importIssueDialogOpenAction(
            selectedFile?.id ?? null,
            file.id,
        );

        if (!action.shouldLoad) {
            setDialogOpen(action.isOpen);

            return;
        }

        router.get(show(budgetId), importIssuePageQuery(currentUrl, file.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setDialogOpen(true),
        });
    };

    return (
        <Dialog open={dialogState.isOpen} onOpenChange={setDialogOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={(event) => {
                        event.preventDefault();
                        openIssues();
                    }}
                >
                    <ListChecks className="size-3" />
                    Ver incidencias
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90vh] overflow-hidden p-0 sm:max-w-3xl lg:max-w-5xl">
                <DialogHeader className="border-b px-6 pt-6 pr-12 pb-4">
                    <DialogTitle>Incidencias de {file.name}</DialogTitle>
                    <DialogDescription>
                        Versión {file.version}. Revisa los hallazgos antes de
                        continuar con este archivo.
                    </DialogDescription>
                    <div
                        className="flex flex-wrap gap-2 pt-1"
                        aria-label="Conteos por gravedad"
                    >
                        {Object.entries(file.issue_counts).map(
                            ([severity, count]) => (
                                <Badge key={severity} variant="outline">
                                    {
                                        severityLabels[
                                            severity as keyof typeof severityLabels
                                        ]
                                    }
                                    : {count}
                                </Badge>
                            ),
                        )}
                    </div>
                </DialogHeader>

                <div className="grid max-h-[calc(90vh-12rem)] gap-3 overflow-y-auto px-4 pb-6 sm:px-6">
                    {!issues || issues.data.length === 0 ? (
                        <p className="rounded-lg bg-muted/50 p-4 text-sm text-muted-foreground">
                            No se encontraron problemas.
                        </p>
                    ) : (
                        issues.data.map((issue) => (
                            <article
                                key={issue.id}
                                className="grid grid-cols-[auto_1fr] gap-3 rounded-lg border p-3"
                            >
                                <IssueIcon issue={issue} />
                                <div className="min-w-0">
                                    <p className="text-sm font-medium">
                                        {issue.message}
                                    </p>
                                    <details className="mt-2 rounded-md bg-muted/50 px-3 py-2 text-xs">
                                        <summary className="cursor-pointer font-medium text-muted-foreground">
                                            Más información
                                        </summary>
                                        <div className="mt-2 grid gap-2">
                                            <p>
                                                <span className="text-muted-foreground">
                                                    Código interno:{' '}
                                                </span>
                                                {issue.code}
                                            </p>
                                            {issue.field && (
                                                <p>
                                                    <span className="text-muted-foreground">
                                                        Campo:{' '}
                                                    </span>
                                                    {issue.field}
                                                </p>
                                            )}
                                            {Object.keys(issue.context).length >
                                                0 && (
                                                <dl className="grid gap-2 sm:grid-cols-2">
                                                    {Object.entries(
                                                        issue.context,
                                                    ).map(([key, value]) => (
                                                        <div
                                                            key={key}
                                                            className="min-w-0"
                                                        >
                                                            <dt className="text-muted-foreground">
                                                                {contextLabels[
                                                                    key
                                                                ] ?? key}
                                                            </dt>
                                                            <dd className="font-medium break-words">
                                                                {String(value)}
                                                            </dd>
                                                        </div>
                                                    ))}
                                                </dl>
                                            )}
                                        </div>
                                    </details>
                                </div>
                            </article>
                        ))
                    )}

                    {issues && issues.last_page > 1 && (
                        <nav
                            className="flex items-center justify-between gap-2"
                            aria-label="Páginas de incidencias"
                        >
                            {issues.current_page > 1 ? (
                                <Button asChild size="sm" variant="outline">
                                    <Link
                                        href={show(budgetId, {
                                            query: importIssuePageQuery(
                                                currentUrl,
                                                file.id,
                                                issues.current_page - 1,
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
                                Página {issues.current_page} de{' '}
                                {issues.last_page}
                            </span>
                            {issues.has_more ? (
                                <Button asChild size="sm" variant="outline">
                                    <Link
                                        href={show(budgetId, {
                                            query: importIssuePageQuery(
                                                currentUrl,
                                                file.id,
                                                issues.current_page + 1,
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
                </div>
            </DialogContent>
        </Dialog>
    );
}
