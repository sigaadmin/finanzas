/**
 * @template {{format: string, activity_code: string, specific_item_code: string, month: number}} T
 * @param {T[]} candidates
 * @param {{format?: string, activity?: string, item?: string, month?: string}} filters
 * @returns {T[]}
 */
export function visibleCutCandidates(candidates, filters) {
    return candidates.filter(
        (candidate) =>
            (!filters.format || candidate.format === filters.format) &&
            (!filters.activity ||
                candidate.activity_code === filters.activity) &&
            (!filters.item || candidate.specific_item_code === filters.item) &&
            (!filters.month || candidate.month === Number(filters.month)),
    );
}

/**
 * @param {string} currentUrl
 * @param {{format?: string, activity?: string, item?: string, month?: string}} filters
 */
export function cutFiltersQuery(currentUrl, filters) {
    const url = new URL(currentUrl, 'https://finanzas.test');

    for (const key of ['format', 'activity', 'item', 'month']) {
        const value = filters[key]?.trim();

        if (value) {
            url.searchParams.set(key, value);
        } else {
            url.searchParams.delete(key);
        }
    }

    return Object.fromEntries(url.searchParams.entries());
}

/** @param {string} cents */
export function cutCentsToPesos(cents) {
    const normalized = cents.trim();

    if (!/^\d+$/.test(normalized)) {
        return '';
    }

    const digits = normalized.replace(/^0+(?=\d)/, '').padStart(3, '0');

    return `${digits.slice(0, -2)}.${digits.slice(-2)}`;
}

/** @param {string} pesos */
export function cutPesosToCents(pesos) {
    const normalized = pesos.trim();

    if (!/^\d+(?:\.\d{0,2})?$/.test(normalized)) {
        return null;
    }

    const [whole, fraction = ''] = normalized.split('.');

    return `${whole}${fraction.padEnd(2, '0')}`.replace(/^0+(?=\d)/, '');
}
