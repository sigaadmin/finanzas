import { useForm } from '@inertiajs/react';
import { Plus, Save, Trash2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { update } from '@/routes/finance/own-revenue/budgets';
import type {
    AnnualValueStatus,
    OwnRevenueAnnualSettingsFormData,
    OwnRevenueBudgetSettings,
    OwnRevenueSignatory,
} from '@/types/finance-own-revenue';

type Props = {
    budgetId: number;
    settings: OwnRevenueBudgetSettings;
    signatories: OwnRevenueSignatory[];
    institutionalOnly?: boolean;
};

type AnnualSettingsFormData = OwnRevenueAnnualSettingsFormData & {
    budget?: string;
};

const statusLabels: Record<AnnualValueStatus, string> = {
    pending_review: 'Pendiente de revisión',
    provisional: 'Provisional',
    final: 'Final',
};

const unsignedBigIntegerMax = '18446744073709551615';
let signatoryClientKey = 0;

function createSignatoryClientKey(): string {
    signatoryClientKey += 1;

    return `new-${signatoryClientKey}`;
}

export function pesosToCents(pesos: string): string | null {
    const normalized = pesos.trim();

    if (normalized === '') {
        return null;
    }

    if (!/^\d+(?:\.\d{0,2})?$/.test(normalized)) {
        return null;
    }

    const [whole, fraction = ''] = normalized.split('.');
    const digits = `${whole}${fraction.padEnd(2, '0')}`.replace(
        /^0+(?=\d)/,
        '',
    );

    return digits.length < unsignedBigIntegerMax.length ||
        (digits.length === unsignedBigIntegerMax.length &&
            digits <= unsignedBigIntegerMax)
        ? digits
        : null;
}

export function centsToPesos(cents: string | null): string {
    if (cents === null) {
        return '';
    }

    const digits = cents.padStart(3, '0');

    return `${digits.slice(0, -2)}.${digits.slice(-2)}`;
}

function statusForValue(
    value: string,
    status: AnnualValueStatus,
): AnnualValueStatus {
    if (value.trim() === '') {
        return 'pending_review';
    }

    return status === 'pending_review' ? 'provisional' : status;
}

export default function AnnualSettingsForm({
    budgetId,
    settings,
    signatories,
    institutionalOnly = false,
}: Props) {
    const form = useForm<AnnualSettingsFormData>({
        institution_name: settings.institution_name,
        responsible_unit_code: settings.responsible_unit_code,
        responsible_unit_name: settings.responsible_unit_name,
        budget_program_code: settings.budget_program_code,
        budget_program_name: settings.budget_program_name,
        component_code: settings.component_code,
        component_name: settings.component_name,
        official_activity_code: settings.official_activity_code,
        official_activity_name: settings.official_activity_name,
        estimated_income_cents: settings.estimated_income_cents,
        cut_percentage: settings.cut_percentage ?? '',
        uma_value: settings.uma_value ?? '',
        uma_status: settings.uma_status,
        fuel_price_per_liter: settings.fuel_price_per_liter ?? '',
        fuel_price_status: settings.fuel_price_status,
        signatories: signatories.map((signatory) => ({
            clientKey: `persisted-${signatory.id}`,
            role_key: signatory.role_key,
            name: signatory.name,
            position: signatory.position,
            academic_degree: signatory.academic_degree ?? '',
            sort_order: signatory.sort_order,
        })),
    });
    const [estimatedIncomePesos, setEstimatedIncomePesos] = useState(() =>
        centsToPesos(settings.estimated_income_cents),
    );
    const [estimatedIncomeError, setEstimatedIncomeError] = useState<string>();

    const errorFor = (key: string): string | undefined =>
        (form.errors as Record<string, string | undefined>)[key];

    const clearSignatoryErrors = (): void => {
        const signatoryErrorKeys = Object.keys(form.errors).filter((key) =>
            key.startsWith('signatories'),
        ) as Array<keyof OwnRevenueAnnualSettingsFormData>;

        form.clearErrors(...signatoryErrorKeys);
    };

    const updateEstimatedIncome = (value: string): void => {
        setEstimatedIncomePesos(value);

        if (value !== '' && !/^\d+(?:\.\d{0,2})?$/.test(value)) {
            setEstimatedIncomeError(
                'Usa pesos con un máximo de dos decimales.',
            );

            return;
        }

        const cents = pesosToCents(value);

        if (value !== '' && cents === null) {
            setEstimatedIncomeError('El importe es demasiado grande.');

            return;
        }

        setEstimatedIncomeError(undefined);
        form.setData('estimated_income_cents', cents);
    };

    const updateAnnualValue = (
        field: 'uma_value' | 'fuel_price_per_liter',
        statusField: 'uma_status' | 'fuel_price_status',
        value: string,
    ): void => {
        form.setData(field, value);
        form.setData(
            statusField,
            statusForValue(value, form.data[statusField]),
        );
    };

    const addSignatory = (): void => {
        clearSignatoryErrors();
        form.setData('signatories', [
            ...form.data.signatories,
            {
                clientKey: createSignatoryClientKey(),
                role_key: '',
                name: '',
                position: '',
                academic_degree: '',
                sort_order: form.data.signatories.length + 1,
            },
        ]);
    };

    const removeSignatory = (index: number): void => {
        clearSignatoryErrors();
        form.setData(
            'signatories',
            form.data.signatories
                .filter((_, signatoryIndex) => signatoryIndex !== index)
                .map((signatory, signatoryIndex) => ({
                    ...signatory,
                    sort_order: signatoryIndex + 1,
                })),
        );
    };

    const updateSignatory = (
        index: number,
        field: 'role_key' | 'name' | 'position' | 'academic_degree',
        value: string,
    ): void => {
        form.setData(
            'signatories',
            form.data.signatories.map((signatory, signatoryIndex) =>
                signatoryIndex === index
                    ? { ...signatory, [field]: value }
                    : signatory,
            ),
        );
    };

    const submit = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (estimatedIncomeError) {
            return;
        }

        form.transform((data) => ({
            ...data,
            signatories: data.signatories.map((signatory) => ({
                role_key: signatory.role_key,
                name: signatory.name,
                position: signatory.position,
                academic_degree: signatory.academic_degree,
                sort_order: signatory.sort_order,
            })),
            uma_status: statusForValue(data.uma_value, data.uma_status),
            fuel_price_status: statusForValue(
                data.fuel_price_per_liter,
                data.fuel_price_status,
            ),
        }));
        form.put(update(budgetId).url, { preserveScroll: true });
    };

    return (
        <form className="grid gap-4" onSubmit={submit}>
            <InputError message={form.errors.budget} role="alert" />
            <Card>
                <CardHeader>
                    <CardTitle>Configuración institucional</CardTitle>
                    <CardDescription>
                        La región, el ejercicio fiscal y el mes presupuestal de
                        combustible son fijos.
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 md:grid-cols-2">
                    <TextField
                        id="institution_name"
                        label="Institución"
                        value={form.data.institution_name}
                        error={form.errors.institution_name}
                        onChange={(value) =>
                            form.setData('institution_name', value)
                        }
                        className="md:col-span-2"
                    />
                    <TextField
                        id="responsible_unit_code"
                        label="Clave de unidad responsable"
                        value={form.data.responsible_unit_code}
                        error={form.errors.responsible_unit_code}
                        onChange={(value) =>
                            form.setData('responsible_unit_code', value)
                        }
                    />
                    <TextField
                        id="responsible_unit_name"
                        label="Unidad responsable"
                        value={form.data.responsible_unit_name}
                        error={form.errors.responsible_unit_name}
                        onChange={(value) =>
                            form.setData('responsible_unit_name', value)
                        }
                    />
                    <TextField
                        id="budget_program_code"
                        label="Clave del programa presupuestario"
                        value={form.data.budget_program_code}
                        error={form.errors.budget_program_code}
                        onChange={(value) =>
                            form.setData('budget_program_code', value)
                        }
                    />
                    <TextField
                        id="budget_program_name"
                        label="Programa presupuestario"
                        value={form.data.budget_program_name}
                        error={form.errors.budget_program_name}
                        onChange={(value) =>
                            form.setData('budget_program_name', value)
                        }
                    />
                    <TextField
                        id="component_code"
                        label="Clave del componente"
                        value={form.data.component_code}
                        error={form.errors.component_code}
                        onChange={(value) =>
                            form.setData('component_code', value)
                        }
                    />
                    <TextField
                        id="component_name"
                        label="Componente"
                        value={form.data.component_name}
                        error={form.errors.component_name}
                        onChange={(value) =>
                            form.setData('component_name', value)
                        }
                    />
                    <TextField
                        id="official_activity_code"
                        label="Clave de actividad oficial"
                        value={form.data.official_activity_code}
                        error={form.errors.official_activity_code}
                        onChange={(value) =>
                            form.setData('official_activity_code', value)
                        }
                    />
                    <TextField
                        id="official_activity_name"
                        label="Actividad oficial"
                        value={form.data.official_activity_name}
                        error={form.errors.official_activity_name}
                        onChange={(value) =>
                            form.setData('official_activity_name', value)
                        }
                    />
                </CardContent>
            </Card>

            {!institutionalOnly && (
                <>
                    <Card>
                        <CardHeader>
                            <CardTitle>Parámetros anuales</CardTitle>
                            <CardDescription>
                                Los importes se conservan sin conversiones de
                                punto flotante.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="estimated_income_pesos">
                                    Ingreso estimado (pesos)
                                </Label>
                                <Input
                                    id="estimated_income_pesos"
                                    type="text"
                                    inputMode="decimal"
                                    value={estimatedIncomePesos}
                                    onChange={(event) =>
                                        updateEstimatedIncome(
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(
                                        estimatedIncomeError ??
                                        form.errors.estimated_income_cents,
                                    )}
                                    aria-describedby={
                                        (estimatedIncomeError ??
                                        form.errors.estimated_income_cents)
                                            ? 'estimated_income_pesos-error'
                                            : undefined
                                    }
                                />
                                <InputError
                                    id="estimated_income_pesos-error"
                                    message={
                                        estimatedIncomeError ??
                                        form.errors.estimated_income_cents
                                    }
                                />
                            </div>
                            <TextField
                                id="cut_percentage"
                                label="Porcentaje de recorte"
                                value={form.data.cut_percentage}
                                error={form.errors.cut_percentage}
                                onChange={(value) =>
                                    form.setData('cut_percentage', value)
                                }
                                inputMode="decimal"
                            />
                            <AnnualValueField
                                id="uma_value"
                                label="UMA"
                                value={form.data.uma_value}
                                status={form.data.uma_status}
                                valueError={form.errors.uma_value}
                                statusError={form.errors.uma_status}
                                onValueChange={(value) =>
                                    updateAnnualValue(
                                        'uma_value',
                                        'uma_status',
                                        value,
                                    )
                                }
                                onStatusChange={(status) =>
                                    form.setData(
                                        'uma_status',
                                        statusForValue(
                                            form.data.uma_value,
                                            status,
                                        ),
                                    )
                                }
                            />
                            <AnnualValueField
                                id="fuel_price_per_liter"
                                label="Combustible por litro"
                                value={form.data.fuel_price_per_liter}
                                status={form.data.fuel_price_status}
                                valueError={form.errors.fuel_price_per_liter}
                                statusError={form.errors.fuel_price_status}
                                onValueChange={(value) =>
                                    updateAnnualValue(
                                        'fuel_price_per_liter',
                                        'fuel_price_status',
                                        value,
                                    )
                                }
                                onStatusChange={(status) =>
                                    form.setData(
                                        'fuel_price_status',
                                        statusForValue(
                                            form.data.fuel_price_per_liter,
                                            status,
                                        ),
                                    )
                                }
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-start justify-between gap-3">
                            <div className="grid gap-1.5">
                                <CardTitle>Firmantes</CardTitle>
                                <CardDescription>
                                    Usa claves canónicas como prepared_by,
                                    reviewed_by o authorized_by.
                                </CardDescription>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addSignatory}
                                disabled={form.data.signatories.length >= 10}
                            >
                                <Plus className="size-4" />
                                Agregar
                            </Button>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            {form.data.signatories.map((signatory, index) => (
                                <fieldset
                                    key={signatory.clientKey}
                                    className="grid gap-3 rounded-lg border p-4 md:grid-cols-2"
                                >
                                    <legend className="px-1 text-sm font-medium">
                                        Firmante {index + 1}
                                    </legend>
                                    <TextField
                                        id={`signatory-${index}-role`}
                                        label="Clave de rol"
                                        value={signatory.role_key}
                                        error={errorFor(
                                            `signatories.${index}.role_key`,
                                        )}
                                        onChange={(value) =>
                                            updateSignatory(
                                                index,
                                                'role_key',
                                                value,
                                            )
                                        }
                                    />
                                    <TextField
                                        id={`signatory-${index}-name`}
                                        label="Nombre"
                                        value={signatory.name}
                                        error={errorFor(
                                            `signatories.${index}.name`,
                                        )}
                                        onChange={(value) =>
                                            updateSignatory(
                                                index,
                                                'name',
                                                value,
                                            )
                                        }
                                    />
                                    <TextField
                                        id={`signatory-${index}-position`}
                                        label="Cargo"
                                        value={signatory.position}
                                        error={errorFor(
                                            `signatories.${index}.position`,
                                        )}
                                        onChange={(value) =>
                                            updateSignatory(
                                                index,
                                                'position',
                                                value,
                                            )
                                        }
                                    />
                                    <TextField
                                        id={`signatory-${index}-degree`}
                                        label="Grado académico (opcional)"
                                        value={signatory.academic_degree}
                                        error={errorFor(
                                            `signatories.${index}.academic_degree`,
                                        )}
                                        onChange={(value) =>
                                            updateSignatory(
                                                index,
                                                'academic_degree',
                                                value,
                                            )
                                        }
                                    />
                                    <div className="md:col-span-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                removeSignatory(index)
                                            }
                                        >
                                            <Trash2 className="size-4" />
                                            Quitar firmante
                                        </Button>
                                    </div>
                                </fieldset>
                            ))}
                            {form.data.signatories.length === 0 && (
                                <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                                    No hay firmantes. Guardar así limpiará la
                                    lista actual.
                                </p>
                            )}
                            <InputError message={form.errors.signatories} />
                        </CardContent>
                    </Card>
                </>
            )}

            <div className="flex justify-end">
                <Button
                    type="submit"
                    disabled={form.processing || Boolean(estimatedIncomeError)}
                >
                    <Save className="size-4" />
                    {form.processing
                        ? 'Guardando…'
                        : institutionalOnly
                          ? 'Guardar fotografía institucional'
                          : 'Guardar configuración'}
                </Button>
            </div>
        </form>
    );
}

type TextFieldProps = {
    id: string;
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
    inputMode?: React.HTMLAttributes<HTMLInputElement>['inputMode'];
    className?: string;
};

function TextField({
    id,
    label,
    value,
    error,
    onChange,
    inputMode,
    className,
}: TextFieldProps) {
    return (
        <div className={`grid gap-2 ${className ?? ''}`}>
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                type="text"
                inputMode={inputMode}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? `${id}-error` : undefined}
            />
            <InputError id={`${id}-error`} message={error} />
        </div>
    );
}

type AnnualValueFieldProps = {
    id: string;
    label: string;
    value: string;
    status: AnnualValueStatus;
    valueError?: string;
    statusError?: string;
    onValueChange: (value: string) => void;
    onStatusChange: (status: AnnualValueStatus) => void;
};

function AnnualValueField({
    id,
    label,
    value,
    status,
    valueError,
    statusError,
    onValueChange,
    onStatusChange,
}: AnnualValueFieldProps) {
    return (
        <fieldset className="grid gap-3 rounded-lg border p-4">
            <legend className="px-1 text-sm font-medium">{label}</legend>
            <div className="grid gap-2">
                <Label htmlFor={id}>Valor</Label>
                <Input
                    id={id}
                    type="text"
                    inputMode="decimal"
                    step="0.0001"
                    value={value}
                    onChange={(event) => onValueChange(event.target.value)}
                    aria-invalid={Boolean(valueError)}
                    aria-describedby={valueError ? `${id}-error` : undefined}
                />
                <InputError id={`${id}-error`} message={valueError} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`${id}-status`}>Estatus</Label>
                <Select
                    value={status}
                    onValueChange={(selected) =>
                        onStatusChange(selected as AnnualValueStatus)
                    }
                >
                    <SelectTrigger
                        id={`${id}-status`}
                        className="w-full"
                        aria-invalid={Boolean(statusError)}
                        aria-describedby={
                            statusError ? `${id}-status-error` : undefined
                        }
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            value="pending_review"
                            disabled={value.trim() !== ''}
                        >
                            Pendiente de revisión
                        </SelectItem>
                        <SelectItem value="provisional">Provisional</SelectItem>
                        <SelectItem value="final">Final</SelectItem>
                    </SelectContent>
                </Select>
                <InputError id={`${id}-status-error`} message={statusError} />
                <p className="text-xs text-muted-foreground">
                    {statusLabels[status]}
                </p>
            </div>
        </fieldset>
    );
}
