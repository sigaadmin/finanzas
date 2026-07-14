export const initialImportMutation = {
    activeFileId: null,
    error: null,
};

const importFileStatusLabels = {
    uploaded: 'Listo para analizar',
    analyzing: 'Analizando',
    needs_correction: 'Requiere atención',
    ready: 'Listo para revisar',
    confirmed: 'Confirmado',
    failed: 'No se pudo analizar',
    parser_pending: 'Revisión automática aún no disponible',
    replaced: 'Reemplazado por otra versión',
    discarded: 'Descartado',
};

export function importFilePresentation({
    status,
    format,
    analyzed,
    issueCount,
}) {
    const isAbpre = format === 'abpre';

    return {
        label: importFileStatusLabels[status],
        canAnalyze:
            isAbpre &&
            ['uploaded', 'needs_correction', 'failed'].includes(status),
        canViewIssues: analyzed || issueCount > 0,
        canViewPreview: isAbpre && analyzed,
    };
}

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
