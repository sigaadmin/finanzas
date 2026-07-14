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
    };

    return messages[state] ?? '';
}

export function canManageWorkSheetDecision({
    canManage,
    decisionsEnabled,
    requiresDecision,
}) {
    return canManage && decisionsEnabled && requiresDecision;
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
