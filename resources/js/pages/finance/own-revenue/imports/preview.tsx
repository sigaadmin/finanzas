import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ShieldCheck } from 'lucide-react';
import AbprePreview from '@/components/finance/own-revenue/imports/abpre-preview';
import { importFilePresentation } from '@/components/finance/own-revenue/imports/import-workspace-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { show as showImports } from '@/routes/finance/own-revenue/budgets/imports';
import type { OwnRevenueAbprePreviewProps } from '@/types/finance-own-revenue-imports';

export default function OwnRevenueAbprePreview({
    budget,
    selected_file: selectedFile,
    preview,
    decision_warnings: decisionWarnings,
    permissions,
}: OwnRevenueAbprePreviewProps) {
    const status = importFilePresentation({
        status: selectedFile.status,
        format: selectedFile.format,
        analyzed: selectedFile.analyzed,
        issueCount:
            selectedFile.issue_counts.error +
            selectedFile.issue_counts.warning +
            selectedFile.issue_counts.info,
        canReclassify: selectedFile.can_reclassify,
    });

    return (
        <>
            <Head title={`Vista previa ABPRE · ${selectedFile.name}`} />
            <main className="flex h-full flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="grid gap-3">
                    <Button asChild variant="ghost" size="sm" className="w-fit">
                        <Link
                            href={showImports(budget.id, {
                                query: { import_file_id: selectedFile.id },
                            })}
                        >
                            <ArrowLeft className="size-4" />
                            Volver a importaciones
                        </Link>
                    </Button>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div className="min-w-0">
                            <p className="text-sm text-muted-foreground">
                                Presupuesto {budget.fiscal_year}
                            </p>
                            <h1 className="mt-1 text-2xl font-semibold">
                                Vista previa ABPRE
                            </h1>
                            <p className="mt-1 text-sm break-words text-muted-foreground">
                                {selectedFile.name} · versión{' '}
                                {selectedFile.version}
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge variant="outline">{status.label}</Badge>
                            <Badge variant="outline">
                                <ShieldCheck className="size-3" />
                                {permissions.confirm
                                    ? 'Confirmación habilitada'
                                    : 'Sólo consulta'}
                            </Badge>
                        </div>
                    </div>
                </header>

                <AbprePreview
                    budgetId={budget.id}
                    file={selectedFile}
                    preview={preview}
                    decisionWarnings={decisionWarnings}
                    permissions={permissions}
                />
            </main>
        </>
    );
}
