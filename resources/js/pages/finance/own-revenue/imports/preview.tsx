import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ShieldCheck } from 'lucide-react';
import AbprePreview from '@/components/finance/own-revenue/imports/abpre-preview';
import { importFilePresentation } from '@/components/finance/own-revenue/imports/import-workspace-state';
import SupportingFormatPreview from '@/components/finance/own-revenue/imports/supporting-format-preview';
import WorkSheetPreview from '@/components/finance/own-revenue/imports/work-sheet-preview';
import { workSheetPreviewBadge } from '@/components/finance/own-revenue/imports/work-sheet-preview-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { show as showImports } from '@/routes/finance/own-revenue/budgets/imports';
import type {
    OwnRevenueAbprePreviewProps,
    OwnRevenueSupportingPreviewProps,
    OwnRevenueWorkSheetPreviewProps,
} from '@/types/finance-own-revenue-imports';

type Props =
    | OwnRevenueAbprePreviewProps
    | OwnRevenueWorkSheetPreviewProps
    | OwnRevenueSupportingPreviewProps;

export default function OwnRevenueImportPreview({
    budget,
    selected_file: selectedFile,
    preview,
    permissions,
    ...props
}: Props) {
    const isWorkSheet = selectedFile.format === 'work_sheet';
    const isAbpre = selectedFile.format === 'abpre';
    const previewFormat = selectedFile.format ?? 'abpre';
    const title = {
        abpre: 'Vista previa ABPRE',
        work_sheet: 'Revisión de Hoja de trabajo',
        technical_sheet: 'Revisión de Ficha técnica',
        fuel: 'Revisión de Combustible',
        travel_expenses: 'Revisión de Viáticos',
    }[previewFormat];
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
    const workSheetProps = props as Omit<
        OwnRevenueWorkSheetPreviewProps,
        'budget' | 'selected_file' | 'preview' | 'permissions'
    >;
    const workSheetStatus = isWorkSheet
        ? workSheetPreviewBadge({
              status: selectedFile.status,
              viewState: workSheetProps.view_state,
              canManage: permissions.manage,
              canConfirm: workSheetProps.can_confirm,
          })
        : null;

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
                            {isWorkSheet ? (
                                <Badge variant="outline">
                                    <ShieldCheck className="size-3" />
                                    {workSheetStatus}
                                </Badge>
                            ) : isAbpre ? (
                                <>
                                    <Badge variant="outline">
                                        {status.label}
                                    </Badge>
                                    <Badge variant="outline">
                                        <ShieldCheck className="size-3" />
                                        {permissions.confirm
                                            ? 'Confirmación habilitada'
                                            : 'Sólo consulta'}
                                    </Badge>
                                </>
                            ) : (
                                <Badge variant="outline">{status.label}</Badge>
                            )}
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
                        blockingIssues={workSheetProps.blocking_issues}
                        reviewIssues={workSheetProps.review_issues}
                        viewState={workSheetProps.view_state}
                        decisionsEnabled={workSheetProps.decisions_enabled}
                        canConfirm={workSheetProps.can_confirm}
                        confirmReasons={workSheetProps.confirm_reasons}
                        permissions={permissions}
                    />
                ) : isAbpre ? (
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
                ) : (
                    <SupportingFormatPreview
                        budget={budget}
                        selected_file={
                            selectedFile as OwnRevenueSupportingPreviewProps['selected_file']
                        }
                        preview={
                            preview as OwnRevenueSupportingPreviewProps['preview']
                        }
                        decision_warnings={
                            (props as OwnRevenueSupportingPreviewProps)
                                .decision_warnings
                        }
                        can_confirm={
                            (props as OwnRevenueSupportingPreviewProps)
                                .can_confirm
                        }
                        confirm_reasons={
                            (props as OwnRevenueSupportingPreviewProps)
                                .confirm_reasons
                        }
                        permissions={permissions}
                    />
                )}
            </main>
        </>
    );
}
