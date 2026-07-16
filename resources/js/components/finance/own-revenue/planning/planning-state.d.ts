import type { PlanningSection } from '@/types/finance-own-revenue';

export function planningSectionQuery(
    currentUrl: string,
    section: PlanningSection,
): Record<string, string>;
export function planningPageQuery(
    currentUrl: string,
    page: number,
): Record<string, string>;
export function planningDetailQuery(
    currentUrl: string,
    detailId: number,
): Record<string, string>;
export function planningVersionQuery(
    currentUrl: string,
    version: number,
): Record<string, string>;
