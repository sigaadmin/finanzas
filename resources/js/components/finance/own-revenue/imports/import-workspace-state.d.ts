export type ImportMutationFeedback = {
    activeFileId: number | null;
    error: string | null;
};

export type UploadFileIdentity = {
    name: string;
    size: number;
    lastModified: number;
};

export const initialImportMutation: ImportMutationFeedback;

export function startImportMutation(
    current: ImportMutationFeedback,
    fileId: number,
): ImportMutationFeedback;

export function failImportMutation(
    current: ImportMutationFeedback,
    errors: Record<string, string>,
): ImportMutationFeedback;

export function finishImportMutation(
    current: ImportMutationFeedback,
): ImportMutationFeedback;

export function resolveFailedUpload<
    TFile extends UploadFileIdentity,
    TFailure extends { file: TFile },
>(failures: TFailure[], resolvedFile: TFile): TFailure[];

export function takeNextUpload<T>(queue: T[]): {
    current: T | null;
    remaining: T[];
};

export function importDecisionRememberKey(
    previewFile: { id: number; analyzed_at: string | null } | null,
): string;
