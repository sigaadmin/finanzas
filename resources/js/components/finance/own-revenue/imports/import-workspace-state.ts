export type ImportMutationFeedback = {
    activeFileId: number | null;
    error: string | null;
};

export type UploadFileIdentity = {
    name: string;
    size: number;
    lastModified: number;
};

export const initialImportMutation: ImportMutationFeedback = {
    activeFileId: null,
    error: null,
};

export function startImportMutation(
    _current: ImportMutationFeedback,
    fileId: number,
): ImportMutationFeedback {
    return { activeFileId: fileId, error: null };
}

export function failImportMutation(
    current: ImportMutationFeedback,
    errors: Record<string, string>,
): ImportMutationFeedback {
    return {
        ...current,
        error:
            errors.file ??
            errors.format ??
            errors.general ??
            Object.values(errors)[0] ??
            'No fue posible completar la operación.',
    };
}

export function finishImportMutation(
    current: ImportMutationFeedback,
): ImportMutationFeedback {
    return { ...current, activeFileId: null };
}

export function resolveFailedUpload<
    TFile extends UploadFileIdentity,
    TFailure extends { file: TFile },
>(failures: TFailure[], resolvedFile: TFile): TFailure[] {
    return failures.filter(
        ({ file }) =>
            file.name !== resolvedFile.name ||
            file.size !== resolvedFile.size ||
            file.lastModified !== resolvedFile.lastModified,
    );
}

export function takeNextUpload<T>(queue: T[]): {
    current: T | null;
    remaining: T[];
} {
    const [current = null, ...remaining] = queue;

    return { current, remaining };
}

export function importDecisionRememberKey(
    previewFile: { id: number; analyzed_at: string | null } | null,
): string {
    return `own-revenue-abpre-decisions-${previewFile?.id ?? 'none'}-${previewFile?.analyzed_at ?? 'unanalyzed'}`;
}
