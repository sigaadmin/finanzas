import { router } from '@inertiajs/react';
import { History, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import planning from '@/routes/finance/own-revenue/budgets/planning';
import fuelNeeds from '@/routes/finance/own-revenue/budgets/proposals/fuel-needs';
import technicalNeeds from '@/routes/finance/own-revenue/budgets/proposals/technical-needs';
import travelCommissions from '@/routes/finance/own-revenue/budgets/proposals/travel-commissions';
import type {
    PlanningPaginator,
    PlanningSection,
} from '@/types/finance-own-revenue';
import { planningDetailQuery, planningPageQuery } from './planning-state.js';

function money(cents: string): string {
    const value = cents.padStart(3, '0');
    const whole = value.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    return `$${whole}.${value.slice(-2)}`;
}

export default function PlanningRecordList({
    budgetId,
    proposalId,
    section,
    rows,
    editable,
}: {
    budgetId: number;
    proposalId: number;
    section: PlanningSection;
    rows: PlanningPaginator;
    editable: boolean;
}) {
    const currentUrl =
        typeof window === 'undefined'
            ? ''
            : `${window.location.pathname}${window.location.search}`;
    const visit = (query: Record<string, string>) =>
        router.get(planning.show(budgetId).url, query, {
            preserveScroll: true,
        });
    const remove = (id: number) => {
        if (!window.confirm('¿Eliminar este registro de la propuesta?')) {
            return;
        }

        const route =
            section === 'technical'
                ? technicalNeeds.destroy([budgetId, proposalId, id])
                : section === 'fuel'
                  ? fuelNeeds.destroy([budgetId, proposalId, id])
                  : travelCommissions.destroy([budgetId, proposalId, id]);
        router.delete(route.url, { preserveScroll: true });
    };

    return (
        <div className="grid gap-3">
            {rows.data.map((row) => {
                const amount =
                    row.budget_amount_cents ?? row.total_amount_cents ?? '0';

                return (
                    <Card key={row.id}>
                        <CardContent className="grid gap-3 py-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="font-medium">{row.title}</p>
                                    <Badge variant="secondary">
                                        {row.activity.code}
                                    </Badge>
                                    <Badge variant="outline">
                                        {row.source_label}
                                    </Badge>
                                </div>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {section === 'technical' &&
                                        `${row.specific_item_code} · ${row.specific_item_name}`}
                                    {section === 'fuel' &&
                                        `${row.total_kilometers} km · mes del recorrido ${row.operational_month} · presupuesto en abril`}
                                    {section === 'travel' &&
                                        `${row.participants_count} participante(s) · mes ${row.operational_month}`}
                                </p>
                            </div>
                            <div className="flex flex-wrap items-center gap-2 lg:justify-end">
                                <p className="mr-2 font-semibold tabular-nums">
                                    {money(amount)}
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        visit(
                                            planningDetailQuery(
                                                currentUrl,
                                                row.id,
                                            ),
                                        )
                                    }
                                >
                                    <History className="size-4" /> Correcciones
                                </Button>
                                {editable && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => remove(row.id)}
                                        aria-label={`Eliminar ${row.title}`}
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                );
            })}
            {rows.data.length === 0 && (
                <div className="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                    Aún no hay registros en esta sección.
                </div>
            )}
            {rows.last_page > 1 && (
                <nav
                    className="flex items-center justify-between gap-3"
                    aria-label="Páginas de la planeación"
                >
                    <Button
                        type="button"
                        variant="outline"
                        disabled={rows.current_page <= 1}
                        onClick={() =>
                            visit(
                                planningPageQuery(
                                    currentUrl,
                                    rows.current_page - 1,
                                ),
                            )
                        }
                    >
                        Anterior
                    </Button>
                    <p className="text-sm text-muted-foreground">
                        Página {rows.current_page} de {rows.last_page}
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={rows.current_page >= rows.last_page}
                        onClick={() =>
                            visit(
                                planningPageQuery(
                                    currentUrl,
                                    rows.current_page + 1,
                                ),
                            )
                        }
                    >
                        Siguiente
                    </Button>
                </nav>
            )}
        </div>
    );
}
