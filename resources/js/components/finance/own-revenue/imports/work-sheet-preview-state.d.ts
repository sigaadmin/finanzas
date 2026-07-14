export type WorkSheetPreviewState =
    | 'not_analyzed'
    | 'analyzing'
    | 'failed'
    | 'empty'
    | 'abpre_changed'
    | 'confirmed'
    | 'replaced'
    | 'discarded'
    | 'ready';

export function formatCents(rawCents: string): string;
export function previewStateMessage(state: WorkSheetPreviewState): string;
export function workSheetPreviewBadge(options: {
    status: string;
    viewState: WorkSheetPreviewState;
    canManage: boolean;
    canConfirm: boolean;
}): string;
export function workSheetDecisionFeedback(
    errors: Partial<Record<string, string>>,
): string;
export function canManageWorkSheetDecision(options: {
    canManage: boolean;
    decisionsEnabled: boolean;
    requiresDecision: boolean;
}): boolean;
export function canConfirmWorkSheet(options: {
    canManage: boolean;
    canConfirm: boolean;
    analysisRevision: string | null;
}): boolean;
export function workSheetConfirmationFeedback(
    errors: Partial<Record<string, string>>,
): string;
export function previewPageQuery(
    currentUrl: string,
    pageName: 'preview_page' | 'blocking_page' | 'review_page',
    page: number,
): Record<string, string>;
