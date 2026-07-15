export function openActivityGroup(currentUrl, group) {
    const query = new URLSearchParams(currentUrl.split('?')[1] ?? '');

    return {
        format: query.get('format') ?? 'technical_sheet',
        group,
    };
}

export function reconciliationStatusLabel(summary) {
    if (summary.total > 0 && summary.pending === 0) {
        return 'Actividades conciliadas';
    }

    return `${summary.pending} ${summary.pending === 1 ? 'registro pendiente' : 'registros pendientes'}`;
}
