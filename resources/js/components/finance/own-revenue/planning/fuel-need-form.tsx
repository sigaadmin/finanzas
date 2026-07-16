import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import fuelNeeds from '@/routes/finance/own-revenue/budgets/proposals/fuel-needs';
import type { PlanningCatalogs } from '@/types/finance-own-revenue';
import {
    PlanningField,
    PlanningInput,
    selectClassName,
} from './planning-form-fields';

export default function FuelNeedForm({
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
                <CardTitle>Agregar recorrido de combustible</CardTitle>
            </CardHeader>
            <CardContent>
                <Form
                    action={fuelNeeds.store([budgetId, proposalId]).url}
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
                                label="Recorrido"
                                name="own_revenue_route_id"
                                error={errors.own_revenue_route_id}
                            >
                                <select
                                    id="own_revenue_route_id"
                                    name="own_revenue_route_id"
                                    className={selectClassName}
                                    required
                                >
                                    <option value="">
                                        Selecciona un recorrido
                                    </option>
                                    {catalogs.routes.map((route) => (
                                        <option key={route.id} value={route.id}>
                                            {route.origin} → {route.destination}
                                        </option>
                                    ))}
                                </select>
                            </PlanningField>
                            <PlanningInput
                                label="Motivo"
                                name="reason"
                                error={errors.reason}
                                required
                                className="md:col-span-2"
                            />
                            <PlanningInput
                                label="Vehículo"
                                name="vehicle_model"
                                error={errors.vehicle_model}
                                required
                            />
                            <PlanningInput
                                label="Rendimiento (km por litro)"
                                name="kilometers_per_liter"
                                error={errors.kilometers_per_liter}
                                inputMode="decimal"
                                required
                            />
                            <PlanningInput
                                label="Mes del recorrido"
                                name="operational_month"
                                error={errors.operational_month}
                                type="number"
                                min={1}
                                max={12}
                                required
                            />
                            <PlanningInput
                                label="Etiqueta de fecha"
                                name="commission_date_label"
                                error={errors.commission_date_label}
                                placeholder="Ej. AGOSTO"
                            />
                            <PlanningInput
                                label="Orden"
                                name="sort_order"
                                type="number"
                                min={0}
                                defaultValue={0}
                            />
                            <PlanningInput
                                label="Justificación de cualquier excepción"
                                name="override_justification"
                                error={errors.override_justification}
                            />
                            <Button
                                type="submit"
                                disabled={processing}
                                className="md:col-span-2 md:w-fit"
                            >
                                {processing
                                    ? 'Guardando…'
                                    : 'Agregar recorrido'}
                            </Button>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}
