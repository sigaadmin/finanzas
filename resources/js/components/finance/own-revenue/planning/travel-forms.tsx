import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import travelCommissions from '@/routes/finance/own-revenue/budgets/proposals/travel-commissions';
import participants from '@/routes/finance/own-revenue/budgets/proposals/travel-commissions/participants';
import type {
    PlanningCatalogs,
    PlanningRow,
} from '@/types/finance-own-revenue';
import {
    PlanningField,
    PlanningInput,
    selectClassName,
} from './planning-form-fields';

export function TravelCommissionForm({
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
                <CardTitle>Agregar comisión de viáticos</CardTitle>
            </CardHeader>
            <CardContent>
                <Form
                    action={travelCommissions.store([budgetId, proposalId]).url}
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
                                label="Destino"
                                name="own_revenue_travel_destination_id"
                                error={errors.own_revenue_travel_destination_id}
                            >
                                <select
                                    id="own_revenue_travel_destination_id"
                                    name="own_revenue_travel_destination_id"
                                    className={selectClassName}
                                    required
                                >
                                    <option value="">
                                        Selecciona un destino
                                    </option>
                                    {catalogs.destinations.map(
                                        (destination) => (
                                            <option
                                                key={destination.id}
                                                value={destination.id}
                                            >
                                                {destination.destination}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </PlanningField>
                            <PlanningInput
                                label="Motivo"
                                name="reason"
                                error={errors.reason}
                                required
                            />
                            <PlanningInput
                                label="Etiqueta de fechas"
                                name="commission_date_label"
                                error={errors.commission_date_label}
                                placeholder="Ej. 12 AL 14 DE AGOSTO"
                            />
                            <PlanningInput
                                label="Mes de la comisión"
                                name="operational_month"
                                error={errors.operational_month}
                                type="number"
                                min={1}
                                max={12}
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
                                label="Transporte aéreo (centavos)"
                                name="flight_amount_cents"
                                error={errors.flight_amount_cents}
                                inputMode="numeric"
                                defaultValue="0"
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
                                {processing ? 'Guardando…' : 'Agregar comisión'}
                            </Button>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}

export function TravelParticipantForm({
    budgetId,
    proposalId,
    commissions,
}: {
    budgetId: number;
    proposalId: number;
    commissions: PlanningRow[];
}) {
    const defaultCommission = commissions[0]?.id;

    if (defaultCommission === undefined) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Agregar participante</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-4">
                <p className="text-sm text-muted-foreground">
                    Selecciona una comisión visible en esta página. Las tarifas
                    se aplicarán según el cargo y las zonas del destino.
                </p>
                {commissions.map((commission) => (
                    <details
                        key={commission.id}
                        className="rounded-lg border p-3"
                    >
                        <summary className="cursor-pointer font-medium">
                            {commission.title} · {commission.reason}
                        </summary>
                        <Form
                            action={
                                participants.store([
                                    budgetId,
                                    proposalId,
                                    commission.id,
                                ]).url
                            }
                            method="post"
                            resetOnSuccess
                            className="mt-4 grid gap-4 md:grid-cols-2"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <PlanningInput
                                        label="Nombre"
                                        name="person_name"
                                        error={errors.person_name}
                                        required
                                    />
                                    <PlanningInput
                                        label="Cargo"
                                        name="position"
                                        error={errors.position}
                                        required
                                    />
                                    <PlanningInput
                                        label="Días de comisión"
                                        name="commission_days"
                                        error={errors.commission_days}
                                        inputMode="decimal"
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
                                        label="Tarifa de alimentación (UMA, opcional)"
                                        name="per_diem_uma"
                                        error={errors.per_diem_uma}
                                        inputMode="decimal"
                                    />
                                    <PlanningInput
                                        label="Tarifa de hospedaje (UMA, opcional)"
                                        name="lodging_uma"
                                        error={errors.lodging_uma}
                                        inputMode="decimal"
                                    />
                                    <PlanningInput
                                        label="Justificación si cambia la tarifa"
                                        name="override_justification"
                                        error={errors.override_justification}
                                    />
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="md:self-end"
                                    >
                                        {processing
                                            ? 'Guardando…'
                                            : 'Agregar participante'}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </details>
                ))}
            </CardContent>
        </Card>
    );
}
