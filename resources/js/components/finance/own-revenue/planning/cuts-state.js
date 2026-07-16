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
