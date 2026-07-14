import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ShieldCheck } from 'lucide-react';
import AbprePreview from '@/components/finance/own-revenue/imports/abpre-preview';
import { importFilePresentation } from '@/components/finance/own-revenue/imports/import-workspace-state';
import WorkSheetPreview from '@/components/finance/own-revenue/imports/work-sheet-preview';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { show as showImports } from '@/routes/finance/own-revenue/budgets/imports';
import type {
    OwnRevenueAbprePreviewProps,
    OwnRevenueWorkSheetPreviewProps,
} from '@/types/finance-own-revenue-imports';

type Props = OwnRevenueAbprePreviewProps | OwnRevenueWorkSheetPreviewProps;

export default function OwnRevenueImportPreview({
    budget,
    selected_file: selectedFile,
    preview,
    permissions,
    ...props
}: Props) {
    const isWorkSheet = selectedFile.format === 'work_sheet';
    const title = isWorkSheet
        ? 'Revisión de Hoja de trabajo'
        : 'Vista previa ABPRE';
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
            <Head title={`${title} · ${selectedFile.name}`} />
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
                                {title}
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

                {isWorkSheet ? (
                    <WorkSheetPreview
                        budgetId={budget.id}
                        file={selectedFile}
                        preview={
                            preview as OwnRevenueWorkSheetPreviewProps['preview']
                        }
                        blockingIssues={
                            (
                                props as Omit<
                                    OwnRevenueWorkSheetPreviewProps,
                                    | 'budget'
                                    | 'selected_file'
                                    | 'preview'
                                    | 'permissions'
                                >
                            ).blocking_issues
                        }
                        reviewIssues={
                            (
                                props as Omit<
                                    OwnRevenueWorkSheetPreviewProps,
                                    | 'budget'
                                    | 'selected_file'
                                    | 'preview'
                                    | 'permissions'
                                >
                            ).review_issues
                        }
                        viewState={
                            (
                                props as Omit<
                                    OwnRevenueWorkSheetPreviewProps,
                                    | 'budget'
                                    | 'selected_file'
                                    | 'preview'
                                    | 'permissions'
                                >
                            ).view_state
                        }
                        decisionsEnabled={
                            (
                                props as Omit<
                                    OwnRevenueWorkSheetPreviewProps,
                                    | 'budget'
                                    | 'selected_file'
                                    | 'preview'
                                    | 'permissions'
                                >
                            ).decisions_enabled
                        }
                        permissions={permissions}
                    />
                ) : (
                    <AbprePreview
                        budgetId={budget.id}
                        file={selectedFile}
                        preview={
                            preview as OwnRevenueAbprePreviewProps['preview']
                        }
                        decisionWarnings={
                            (
                                props as Omit<
                                    OwnRevenueAbprePreviewProps,
                                    | 'budget'
                                    | 'selected_file'
                                    | 'preview'
                                    | 'permissions'
                                >
                            ).decision_warnings
                        }
                        permissions={permissions}
                    />
                )}
            </main>
        </>
    );
}
