export type WorkSheetPreviewState =
    | 'not_analyzed'
    | 'analyzing'
    | 'failed'
    | 'empty'
    | 'ready';

export function formatCents(rawCents: string): string;
export function previewStateMessage(state: WorkSheetPreviewState): string;
export function workSheetDecisionFeedback(
    errors: Partial<Record<string, string>>,
): string;
