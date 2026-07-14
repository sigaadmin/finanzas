export function formatCents(rawCents) {
    const negative = rawCents.startsWith('-');
    const digits = (negative ? rawCents.slice(1) : rawCents)
        .replace(/\D/g, '')
        .replace(/^0+(?=\d)/, '')
        .padStart(3, '0');
    const whole = digits.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const fraction = digits.slice(-2);

    return `${negative ? '-' : ''}$${whole}.${fraction}`;
}

export function previewStateMessage(state) {
    const messages = {
        not_analyzed: 'Analiza este archivo para consultar sus renglones.',
        analyzing:
            'La revisión está en proceso. Actualiza la página en unos momentos.',
        failed: 'No fue posible preparar la vista previa. Vuelve a analizar el archivo desde Importaciones.',
        empty: 'El análisis no encontró renglones que puedan mostrarse.',
        abpre_changed:
            'El ABPRE cambió; vuelve a analizar la Hoja de trabajo antes de tomar decisiones.',
        replaced: 'Esta versión fue reemplazada por una revisión posterior.',
        discarded:
            'Esta versión fue descartada y se conserva sólo para consulta.',
    };

    return messages[state] ?? '';
}

export function workSheetPreviewBadge({
    status,
    viewState,
    canManage,
    canConfirm,
}) {
    if (status === 'confirmed' || viewState === 'confirmed') {
        return 'Confirmado';
    }

    if (!canManage) {
        return 'Sólo consulta';
    }

    if (canConfirm) {
        return 'Listo para confirmar';
    }

    const labels = {
        not_analyzed: 'Listo para analizar',
        analyzing: 'Analizando',
        failed: 'No se pudo analizar',
        empty: 'Sin información para confirmar',
        abpre_changed: 'Requiere nuevo análisis',
        ready: 'Pendiente de revisión',
        replaced: 'Reemplazada',
        discarded: 'Descartada',
    };

    return labels[viewState] ?? 'Pendiente de revisión';
}

export function canManageWorkSheetDecision({
    canManage,
    decisionsEnabled,
    requiresDecision,
}) {
    return canManage && decisionsEnabled && requiresDecision;
}

export function canConfirmWorkSheet({
    canManage,
    canConfirm,
    analysisRevision,
}) {
    return canManage && canConfirm && analysisRevision !== null;
}

export function previewPageQuery(currentUrl, pageName, page) {
    const query = new URLSearchParams(currentUrl.split('?')[1] ?? '');
    query.set(pageName, String(page));

    return Object.fromEntries(query.entries());
}

export function workSheetDecisionFeedback(errors) {
    if (errors.analysis_revision) {
        return 'La revisión cambió mientras trabajabas. Actualiza la página y revisa nuevamente las diferencias.';
    }

    if (errors.file) {
        return 'La información del presupuesto cambió. Vuelve a analizar el archivo antes de decidir.';
    }

    return errors.decision ?? '';
}

export function workSheetConfirmationFeedback(errors) {
    if (errors.analysis_revision) {
        return 'La revisión cambió mientras confirmabas. Actualiza la página y revisa nuevamente la Hoja de trabajo.';
    }

    return errors.file ?? errors.decisions ?? '';
}
