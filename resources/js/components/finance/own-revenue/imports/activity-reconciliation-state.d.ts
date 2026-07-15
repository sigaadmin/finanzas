export function openActivityGroup(
    currentUrl: string,
    group: string,
): { format: string; group: string };

export function reconciliationStatusLabel(summary: {
    total: number;
    pending: number;
}): string;
