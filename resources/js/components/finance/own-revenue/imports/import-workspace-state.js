export const initialImportMutation = {
    activeFileId: null,
    error: null,
};

export function startImportMutation(_current, fileId) {
    return { activeFileId: fileId, error: null };
}

export function failImportMutation(current, errors) {
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

export function finishImportMutation(current) {
    return { ...current, activeFileId: null };
}

export function resolveFailedUpload(failures, resolvedFile) {
    return failures.filter(
        ({ file }) =>
            file.name !== resolvedFile.name ||
            file.size !== resolvedFile.size ||
            file.lastModified !== resolvedFile.lastModified,
    );
}

export function takeNextUpload(queue) {
    const [current = null, ...remaining] = queue;

    return { current, remaining };
}

export function importDecisionRememberKey(previewFile) {
    return `own-revenue-abpre-decisions-${previewFile?.id ?? 'none'}-${previewFile?.analyzed_at ?? 'unanalyzed'}`;
}
