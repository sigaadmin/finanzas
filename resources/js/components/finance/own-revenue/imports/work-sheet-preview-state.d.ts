export type WorkSheetPreviewState =
    | 'not_analyzed'
    | 'analyzing'
    | 'failed'
    | 'empty'
    | 'abpre_changed'
    | 'ready';

export function formatCents(rawCents: string): string;
export function previewStateMessage(state: WorkSheetPreviewState): string;
export function workSheetDecisionFeedback(
    errors: Partial<Record<string, string>>,
): string;
export function canManageWorkSheetDecision(options: {
    canManage: boolean;
    decisionsEnabled: boolean;
    requiresDecision: boolean;
}): boolean;
export function previewPageQuery(
    currentUrl: string,
    pageName: 'preview_page' | 'blocking_page' | 'review_page',
    page: number,
): Record<string, string>;
