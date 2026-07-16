import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import technicalNeeds from '@/routes/finance/own-revenue/budgets/proposals/technical-needs';
import type { PlanningCatalogs } from '@/types/finance-own-revenue';
import {
    PlanningField,
    PlanningInput,
    selectClassName,
} from './planning-form-fields';

export default function TechnicalNeedForm({
    budgetId,
    proposalId,
    catalogs,
}: {
    budgetId: number;
    proposalId: number;
    catalogs: PlanningCatalogs;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Agregar concepto a Ficha técnica</CardTitle>
            </CardHeader>
            <CardContent>
                <Form
                    action={technicalNeeds.store([budgetId, proposalId]).url}
                    method="post"
                    resetOnSuccess
                    className="grid gap-4 md:grid-cols-2"
                >
                    {({ errors, processing }) => (
                        <>
                            <PlanningField
                                label="Actividad"
                                name="own_revenue_activity_id"
                                error={errors.own_revenue_activity_id}
                            >
                                <select
                                    id="own_revenue_activity_id"
                                    name="own_revenue_activity_id"
                                    className={selectClassName}
                                    required
                                >
                                    <option value="">
                                        Selecciona una actividad
                                    </option>
                                    {catalogs.activities.map((activity) => (
                                        <option
                                            key={activity.id}
                                            value={activity.id}
                                        >
                                            {activity.code} · {activity.name}
                                        </option>
                                    ))}
                                </select>
                            </PlanningField>
                            <PlanningField
                                label="Partida específica"
                                name="expense_classification_id"
                                error={errors.expense_classification_id}
                            >
                                <select
                                    id="expense_classification_id"
                                    name="expense_classification_id"
                                    className={selectClassName}
                                    required
                                >
                                    <option value="">
                                        Selecciona una partida
                                    </option>
                                    {catalogs.expense_classifications.map(
                                        (item) => (
                                            <option
                                                key={item.id}
                                                value={item.id}
                                            >
                                                {item.specific_item_code} ·{' '}
                                                {item.specific_item_name}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </PlanningField>
                            <PlanningInput
                                label="Descripción"
                                name="description"
                                error={errors.description}
                                required
                                className="md:col-span-2"
                            />
                            <PlanningInput
                                label="Cantidad"
                                name="quantity"
                                error={errors.quantity}
                                inputMode="decimal"
                                required
                            />
                            <PlanningInput
                                label="Unidad"
                                name="unit"
                                error={errors.unit}
                                required
                            />
                            <PlanningInput
                                label="Precio unitario (pesos)"
                                name="unit_price"
                                error={errors.unit_price}
                                inputMode="decimal"
                                required
                            />
                            <PlanningInput
                                label="Importe definitivo (centavos)"
                                name="budget_amount_cents"
                                error={errors.budget_amount_cents}
                                inputMode="numeric"
                                required
                            />
                            <PlanningInput
                                label="Mes presupuestal"
                                name="budget_month"
                                error={errors.budget_month}
                                type="number"
                                min={1}
                                max={12}
                                required
                            />
                            <PlanningInput
                                label="Orden"
                                name="sort_order"
                                type="number"
                                min={0}
                                defaultValue={0}
                            />
                            <PlanningInput
                                label="Justificación si el importe cambia"
                                name="override_justification"
                                error={errors.override_justification}
                                className="md:col-span-2"
                            />
                            <Button
                                type="submit"
                                disabled={processing}
                                className="md:col-span-2 md:w-fit"
                            >
                                {processing ? 'Guardando…' : 'Agregar concepto'}
                            </Button>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}
