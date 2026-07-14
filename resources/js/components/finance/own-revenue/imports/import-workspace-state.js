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
    parser_pending: 'Revisión no disponible',
    replaced: 'Reemplazado por otra versión',
    discarded: 'Descartado',
};

export function importFilePresentation({
    status,
    format,
    analyzed,
    issueCount,
    canReclassify,
}) {
    const isAbpre = format === 'abpre';
    const isSupportingFormat = [
        'work_sheet',
        'technical_sheet',
        'fuel',
        'travel_expenses',
    ].includes(format);
    const supportingLabels = {
        uploaded: 'Listo para analizar',
        parser_pending: 'Listo para analizar',
        analyzing: 'Analizando',
        needs_correction: 'Requiere revisión',
        ready: 'Listo para revisar',
        confirmed: 'Confirmado',
        failed: 'No se pudo analizar',
    };

    return {
        label:
            format === null && canReclassify
                ? 'Pendiente de clasificar'
                : isSupportingFormat && supportingLabels[status]
                  ? supportingLabels[status]
                  : importFileStatusLabels[status],
        canAnalyze:
            (isAbpre &&
                ['uploaded', 'needs_correction', 'failed'].includes(status)) ||
            (isSupportingFormat &&
                [
                    'uploaded',
                    'parser_pending',
                    'needs_correction',
                    'failed',
                ].includes(status)),
        canViewIssues: analyzed || issueCount > 0,
        canViewPreview: (isAbpre || isSupportingFormat) && analyzed,
    };
}

export function importFileProgressLabel(status, format) {
    if (status !== 'ready') {
        return null;
    }

    return format === 'abpre' ? 'Listo para confirmar' : 'Listo para revisar';
}

function queryFromUrl(currentUrl) {
    return new URLSearchParams(currentUrl.split('?')[1] ?? '');
}

export function selectImportFileQuery(currentUrl, fileId) {
    const query = queryFromUrl(currentUrl);
    query.set('import_file_id', String(fileId));
    query.delete('issues_page');
    query.delete('preview_page');
    query.delete('decisions_page');

    return Object.fromEntries(query.entries());
}

export function importIssuePageQuery(currentUrl, fileId, page = 1) {
    const query = queryFromUrl(currentUrl);
    query.set('import_file_id', String(fileId));
    query.set('issues_page', String(page));

    return Object.fromEntries(query.entries());
}

export function importIssueDialogState(_current, isOpen) {
    return { isOpen };
}

export function importIssueDialogOpenAction(selectedFileId, fileId) {
    const hasSelectedFile = selectedFileId === fileId;

    return {
        isOpen: hasSelectedFile,
        shouldLoad: !hasSelectedFile,
    };
}

const issueContextLabels = {
    sheet_name: 'Hoja',
    row_number: 'Renglón',
    activity_code: 'Actividad',
    activity_name: 'Nombre de la actividad',
    activity: 'Actividad encontrada',
    item_name: 'Insumo',
    item_names: 'Insumos encontrados',
    source_rows: 'Renglones de origen',
    detected_year: 'Año detectado',
    fiscal_year: 'Año fiscal',
    responsible_unit_code: 'Unidad responsable',
    specific_item_code: 'Partida',
    source_region: 'Región original',
    normalized_region: 'Región asignada',
    source_cents: 'Importe del archivo',
    calculated_cents: 'Importe calculado',
    work_sheet_total_cents: 'Total en Hoja de trabajo',
    abpre_total_cents: 'Total oficial en ABPRE',
    difference_cents: 'Diferencia',
    work_sheet_source_rows: 'Renglones de origen',
    requires_decision: 'Acción necesaria',
    requires_reanalysis: 'Acción necesaria',
};

function issueCents(rawValue) {
    const rawCents = String(rawValue);

    if (!/^-?\d+$/.test(rawCents)) {
        return null;
    }

    const negative = rawCents.startsWith('-');
    const digits = (negative ? rawCents.slice(1) : rawCents)
        .replace(/^0+(?=\d)/, '')
        .padStart(3, '0');
    const whole = digits.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    return `${negative ? '-' : ''}$${whole}.${digits.slice(-2)}`;
}

function issueContextValue(key, value) {
    if (key.endsWith('_cents')) {
        return issueCents(value);
    }

    if (key === 'requires_reanalysis') {
        return value === true ? 'Volver a analizar' : null;
    }

    if (key === 'requires_decision') {
        return value === true ? 'Revisar antes de continuar' : null;
    }

    if (
        ['source_rows', 'work_sheet_source_rows'].includes(key) &&
        Array.isArray(value)
    ) {
        return value.every((row) => Number.isInteger(row))
            ? value.join(', ')
            : null;
    }

    if (key === 'item_names' && Array.isArray(value)) {
        return value.every((item) => typeof item === 'string')
            ? value.join(', ')
            : null;
    }

    return typeof value === 'string' || typeof value === 'number'
        ? String(value)
        : null;
}

export function importIssueContextDetails(context) {
    return Object.entries(context).flatMap(([key, value]) => {
        const label = issueContextLabels[key];

        if (!label) {
            return [];
        }

        const presentedValue = issueContextValue(key, value);

        return presentedValue === null
            ? []
            : [{ label, value: presentedValue }];
    });
}

export function importIssuePresentationKey(page, index, message) {
    return `${page}-${index}-${message}`;
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
