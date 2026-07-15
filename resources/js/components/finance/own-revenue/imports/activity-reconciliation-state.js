export function openActivityGroup(currentUrl, group) {
    const query = new URLSearchParams(currentUrl.split('?')[1] ?? '');
    const params = Object.fromEntries(query.entries());

    params.group = group;

    if (!params.format) {
        params.format = 'technical_sheet';
    }

    return params;
}

export function reconciliationStatusLabel(summary) {
    if (summary.total > 0 && summary.pending === 0) {
        return 'Actividades conciliadas';
    }

    return `${summary.pending} ${summary.pending === 1 ? 'registro pendiente' : 'registros pendientes'}`;
}
