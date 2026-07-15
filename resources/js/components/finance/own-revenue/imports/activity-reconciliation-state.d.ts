export function openActivityGroup(
    currentUrl: string,
    group: string,
): Record<string, string>;

export function reconciliationStatusLabel(summary: {
    total: number;
    pending: number;
}): string;
