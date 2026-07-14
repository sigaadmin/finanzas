import { Link, usePage } from '@inertiajs/react';
import { AlertCircle, Info, TriangleAlert } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { show } from '@/routes/finance/own-revenue/budgets/imports';
import type {
    OwnRevenueImportIssue,
    OwnRevenueSelectedImportFile,
} from '@/types/finance-own-revenue-imports';

type Props = {
    budgetId: number;
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

function queryFor(
    currentUrl: string,
    fileId: number,
    page: number,
): Record<string, string> {
    const query = new URLSearchParams(currentUrl.split('?')[1] ?? '');
    query.set('import_file_id', String(fileId));
    query.set('issues_page', String(page));

    return Object.fromEntries(query.entries());
}

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

export default function ImportIssueList({ budgetId, selectedFile }: Props) {
    const currentUrl = usePage().url;

    return (
        <Card>
            <CardHeader>
                <CardTitle>Incidencias del archivo</CardTitle>
                <CardDescription>
                    {selectedFile
                        ? `${selectedFile.issues.total} incidencias en ${selectedFile.name}`
                        : 'Selecciona una versión para consultar sus incidencias.'}
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-3">
                {!selectedFile || selectedFile.issues.data.length === 0 ? (
                    <p className="rounded-lg bg-muted/50 p-4 text-sm text-muted-foreground">
                        {selectedFile
                            ? 'No se detectaron incidencias.'
                            : 'Sin archivo seleccionado.'}
                    </p>
                ) : (
                    selectedFile.issues.data.map((issue) => (
                        <article
                            key={issue.id}
                            className="grid grid-cols-[auto_1fr] gap-3 rounded-lg border p-3"
                        >
                            <IssueIcon issue={issue} />
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="text-sm font-medium">
                                        {issue.message}
                                    </p>
                                    <Badge variant="outline">
                                        {issue.code}
                                    </Badge>
                                </div>
                                {issue.field && (
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Campo: {issue.field}
                                    </p>
                                )}
                                {Object.keys(issue.context).length > 0 && (
                                    <dl className="mt-2 grid gap-1 rounded-md bg-muted/50 p-2 text-xs sm:grid-cols-2">
                                        {Object.entries(issue.context).map(
                                            ([key, value]) => (
                                                <div
                                                    key={key}
                                                    className="min-w-0"
                                                >
                                                    <dt className="text-muted-foreground">
                                                        {contextLabels[key] ??
                                                            key}
                                                    </dt>
                                                    <dd className="font-medium break-words">
                                                        {String(value)}
                                                    </dd>
                                                </div>
                                            ),
                                        )}
                                    </dl>
                                )}
                            </div>
                        </article>
                    ))
                )}

                {selectedFile && selectedFile.issues.last_page > 1 && (
                    <nav
                        className="flex items-center justify-between gap-2"
                        aria-label="Páginas de incidencias"
                    >
                        {selectedFile.issues.current_page > 1 ? (
                            <Button asChild size="sm" variant="outline">
                                <Link
                                    href={show(budgetId, {
                                        query: queryFor(
                                            currentUrl,
                                            selectedFile.id,
                                            selectedFile.issues.current_page -
                                                1,
                                        ),
                                    })}
                                    preserveScroll
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
                            Página {selectedFile.issues.current_page} de{' '}
                            {selectedFile.issues.last_page}
                        </span>
                        {selectedFile.issues.has_more ? (
                            <Button asChild size="sm" variant="outline">
                                <Link
                                    href={show(budgetId, {
                                        query: queryFor(
                                            currentUrl,
                                            selectedFile.id,
                                            selectedFile.issues.current_page +
                                                1,
                                        ),
                                    })}
                                    preserveScroll
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
            </CardContent>
        </Card>
    );
}
