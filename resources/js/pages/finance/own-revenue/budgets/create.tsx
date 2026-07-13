import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Copy, FilePlus2 } from 'lucide-react';
import { useState } from 'react';
import {
    centsToPesos,
    pesosToCents,
} from '@/components/finance/own-revenue/annual-settings-form';
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
import { create, index, store } from '@/routes/finance/own-revenue/budgets';
import type {
    AnnualValueStatus,
    OwnRevenueSourceBudget,
} from '@/types/finance-own-revenue';

type Props = {
    sourceBudgets: OwnRevenueSourceBudget[];
    permissions: { create: boolean };
};

type CreateBudgetFormData = {
    source_budget_id: string;
    fiscal_year: string;
    institution_name: string;
    responsible_unit_code: string;
    responsible_unit_name: string;
    budget_program_code: string;
    budget_program_name: string;
    component_code: string;
    component_name: string;
    official_activity_code: string;
    official_activity_name: string;
    estimated_income_cents: number | null;
    cut_percentage: string;
    uma_value: string;
    uma_status: AnnualValueStatus;
    fuel_price_per_liter: string;
    fuel_price_status: AnnualValueStatus;
};

type Mode = 'blank' | 'copy';

function coherentStatus(
    value: string,
    status: AnnualValueStatus,
): AnnualValueStatus {
    if (value.trim() === '') {
        return 'pending_review';
    }

    return status === 'pending_review' ? 'provisional' : status;
}

export default function OwnRevenueBudgetCreate({ sourceBudgets }: Props) {
    const page = usePage();
    const requestedSourceBudgetId = new URLSearchParams(
        page.url.split('?')[1] ?? '',
    ).get('source_budget_id');
    const preselectedSourceBudget =
        requestedSourceBudgetId !== null &&
        sourceBudgets.some(
            (sourceBudget) =>
                String(sourceBudget.id) === requestedSourceBudgetId,
        )
            ? requestedSourceBudgetId
            : '';
    const [mode, setMode] = useState<Mode>(
        preselectedSourceBudget === '' ? 'blank' : 'copy',
    );
    const [estimatedIncomePesos, setEstimatedIncomePesos] = useState('');
    const [estimatedIncomeError, setEstimatedIncomeError] = useState<string>();
    const form = useForm<CreateBudgetFormData>({
        source_budget_id: preselectedSourceBudget,
        fiscal_year: '',
        institution_name: '',
        responsible_unit_code: '',
        responsible_unit_name: '',
        budget_program_code: '',
        budget_program_name: '',
        component_code: '',
        component_name: '',
        official_activity_code: '',
        official_activity_name: '',
        estimated_income_cents: null,
        cut_percentage: '',
        uma_value: '',
        uma_status: 'pending_review',
        fuel_price_per_liter: '',
        fuel_price_status: 'pending_review',
    });

    const setAnnualValue = (
        field: 'uma_value' | 'fuel_price_per_liter',
        statusField: 'uma_status' | 'fuel_price_status',
        value: string,
    ): void => {
        form.setData(field, value);
        form.setData(
            statusField,
            coherentStatus(value, form.data[statusField]),
        );
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

    const changeMode = (nextMode: Mode): void => {
        setMode(nextMode);
        form.clearErrors();

        if (nextMode === 'blank') {
            form.setData('source_budget_id', '');
        }
    };

    const submit = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (estimatedIncomeError) {
            return;
        }

        form.transform((data) => {
            if (mode === 'copy') {
                return {
                    source_budget_id: data.source_budget_id,
                    fiscal_year: data.fiscal_year,
                };
            }

            return {
                fiscal_year: data.fiscal_year,
                institution_name: data.institution_name,
                responsible_unit_code: data.responsible_unit_code,
                responsible_unit_name: data.responsible_unit_name,
                budget_program_code: data.budget_program_code,
                budget_program_name: data.budget_program_name,
                component_code: data.component_code,
                component_name: data.component_name,
                official_activity_code: data.official_activity_code,
                official_activity_name: data.official_activity_name,
                estimated_income_cents: data.estimated_income_cents,
                cut_percentage: data.cut_percentage,
                uma_value: data.uma_value,
                uma_status: coherentStatus(data.uma_value, data.uma_status),
                fuel_price_per_liter: data.fuel_price_per_liter,
                fuel_price_status: coherentStatus(
                    data.fuel_price_per_liter,
                    data.fuel_price_status,
                ),
            };
        });
        form.post(store().url);
    };

    return (
        <>
            <Head title="Nuevo presupuesto de ingresos propios" />
            <main className="flex h-full flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="grid gap-3">
                    <Button asChild variant="ghost" size="sm" className="w-fit">
                        <Link href={index()}>
                            <ArrowLeft className="size-4" />
                            Volver al listado
                        </Link>
                    </Button>
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Ingresos propios
                        </p>
                        <h1 className="text-2xl font-semibold">
                            Nuevo presupuesto anual
                        </h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                            Inicia un ejercicio en blanco o copia la
                            configuración de un año anterior.
                        </p>
                    </div>
                </header>

                <div className="grid gap-3 sm:grid-cols-2">
                    <button
                        type="button"
                        onClick={() => changeMode('blank')}
                        aria-pressed={mode === 'blank'}
                        className={`flex items-start gap-3 rounded-lg border p-4 text-left transition-colors ${mode === 'blank' ? 'border-primary bg-primary/5' : 'hover:bg-muted/50'}`}
                    >
                        <FilePlus2 className="mt-0.5 size-5" />
                        <span>
                            <span className="block font-medium">En blanco</span>
                            <span className="text-sm text-muted-foreground">
                                Captura la fotografía institucional y los
                                parámetros del año.
                            </span>
                        </span>
                    </button>
                    <button
                        type="button"
                        onClick={() => changeMode('copy')}
                        aria-pressed={mode === 'copy'}
                        className={`flex items-start gap-3 rounded-lg border p-4 text-left transition-colors ${mode === 'copy' ? 'border-primary bg-primary/5' : 'hover:bg-muted/50'}`}
                    >
                        <Copy className="mt-0.5 size-5" />
                        <span>
                            <span className="block font-medium">
                                Copiar ejercicio
                            </span>
                            <span className="text-sm text-muted-foreground">
                                Parte de un presupuesto anterior y revisa los
                                datos anuales.
                            </span>
                        </span>
                    </button>
                </div>

                <form className="grid gap-4" onSubmit={submit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Ejercicio fiscal</CardTitle>
                            <CardDescription>
                                Debe ser un año de cuatro dígitos que aún no
                                exista.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 md:grid-cols-2">
                            <TextField
                                id="fiscal_year"
                                label="Año fiscal"
                                value={form.data.fiscal_year}
                                error={form.errors.fiscal_year}
                                onChange={(value) =>
                                    form.setData('fiscal_year', value)
                                }
                                inputMode="numeric"
                            />
                            {mode === 'copy' && (
                                <div className="grid gap-2">
                                    <Label htmlFor="source_budget_id">
                                        Presupuesto de origen
                                    </Label>
                                    <select
                                        id="source_budget_id"
                                        value={form.data.source_budget_id}
                                        onChange={(event) =>
                                            form.setData(
                                                'source_budget_id',
                                                event.target.value,
                                            )
                                        }
                                        className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50"
                                        aria-invalid={Boolean(
                                            form.errors.source_budget_id,
                                        )}
                                    >
                                        <option value="">
                                            Selecciona un ejercicio
                                        </option>
                                        {sourceBudgets.map((source) => (
                                            <option
                                                key={source.id}
                                                value={source.id}
                                            >
                                                {source.fiscal_year}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={form.errors.source_budget_id}
                                    />
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {mode === 'copy' ? (
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    Revisión posterior obligatoria
                                </CardTitle>
                                <CardDescription>
                                    La copia conserva la fotografía
                                    institucional y replica COG, ingresos y
                                    porcentaje de recorte. Antes de usarla debes
                                    revisar UMA, combustible, COG, ingreso
                                    estimado y recorte del nuevo año.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    Por seguridad, esta solicitud enviará
                                    únicamente el presupuesto de origen y el
                                    nuevo ejercicio fiscal.
                                </p>
                            </CardContent>
                        </Card>
                    ) : (
                        <>
                            <Card>
                                <CardHeader>
                                    <CardTitle>
                                        Fotografía institucional
                                    </CardTitle>
                                    <CardDescription>
                                        Datos vigentes al iniciar el ejercicio.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4 md:grid-cols-2">
                                    <TextField
                                        id="institution_name"
                                        label="Institución"
                                        value={form.data.institution_name}
                                        error={form.errors.institution_name}
                                        onChange={(value) =>
                                            form.setData(
                                                'institution_name',
                                                value,
                                            )
                                        }
                                        className="md:col-span-2"
                                    />
                                    <TextField
                                        id="responsible_unit_code"
                                        label="Clave de unidad responsable"
                                        value={form.data.responsible_unit_code}
                                        error={
                                            form.errors.responsible_unit_code
                                        }
                                        onChange={(value) =>
                                            form.setData(
                                                'responsible_unit_code',
                                                value,
                                            )
                                        }
                                    />
                                    <TextField
                                        id="responsible_unit_name"
                                        label="Unidad responsable"
                                        value={form.data.responsible_unit_name}
                                        error={
                                            form.errors.responsible_unit_name
                                        }
                                        onChange={(value) =>
                                            form.setData(
                                                'responsible_unit_name',
                                                value,
                                            )
                                        }
                                    />
                                    <TextField
                                        id="budget_program_code"
                                        label="Clave del programa presupuestario"
                                        value={form.data.budget_program_code}
                                        error={form.errors.budget_program_code}
                                        onChange={(value) =>
                                            form.setData(
                                                'budget_program_code',
                                                value,
                                            )
                                        }
                                    />
                                    <TextField
                                        id="budget_program_name"
                                        label="Programa presupuestario"
                                        value={form.data.budget_program_name}
                                        error={form.errors.budget_program_name}
                                        onChange={(value) =>
                                            form.setData(
                                                'budget_program_name',
                                                value,
                                            )
                                        }
                                    />
                                    <TextField
                                        id="component_code"
                                        label="Clave del componente"
                                        value={form.data.component_code}
                                        error={form.errors.component_code}
                                        onChange={(value) =>
                                            form.setData(
                                                'component_code',
                                                value,
                                            )
                                        }
                                    />
                                    <TextField
                                        id="component_name"
                                        label="Componente"
                                        value={form.data.component_name}
                                        error={form.errors.component_name}
                                        onChange={(value) =>
                                            form.setData(
                                                'component_name',
                                                value,
                                            )
                                        }
                                    />
                                    <TextField
                                        id="official_activity_code"
                                        label="Clave de actividad oficial"
                                        value={form.data.official_activity_code}
                                        error={
                                            form.errors.official_activity_code
                                        }
                                        onChange={(value) =>
                                            form.setData(
                                                'official_activity_code',
                                                value,
                                            )
                                        }
                                    />
                                    <TextField
                                        id="official_activity_name"
                                        label="Actividad oficial"
                                        value={form.data.official_activity_name}
                                        error={
                                            form.errors.official_activity_name
                                        }
                                        onChange={(value) =>
                                            form.setData(
                                                'official_activity_name',
                                                value,
                                            )
                                        }
                                    />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle>Parámetros iniciales</CardTitle>
                                    <CardDescription>
                                        Son opcionales y pueden quedar
                                        pendientes de revisión.
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
                                                form.errors
                                                    .estimated_income_cents,
                                            )}
                                        />
                                        <InputError
                                            message={
                                                estimatedIncomeError ??
                                                form.errors
                                                    .estimated_income_cents
                                            }
                                        />
                                        {form.data.estimated_income_cents !==
                                            null && (
                                            <p className="text-xs text-muted-foreground">
                                                Se guardarán{' '}
                                                {form.data.estimated_income_cents.toLocaleString(
                                                    'es-MX',
                                                )}{' '}
                                                centavos (
                                                {centsToPesos(
                                                    form.data
                                                        .estimated_income_cents,
                                                )}{' '}
                                                pesos).
                                            </p>
                                        )}
                                    </div>
                                    <TextField
                                        id="cut_percentage"
                                        label="Porcentaje de recorte"
                                        value={form.data.cut_percentage}
                                        error={form.errors.cut_percentage}
                                        onChange={(value) =>
                                            form.setData(
                                                'cut_percentage',
                                                value,
                                            )
                                        }
                                        inputMode="decimal"
                                    />
                                    <AnnualField
                                        id="uma_value"
                                        label="UMA"
                                        value={form.data.uma_value}
                                        status={form.data.uma_status}
                                        valueError={form.errors.uma_value}
                                        statusError={form.errors.uma_status}
                                        onValueChange={(value) =>
                                            setAnnualValue(
                                                'uma_value',
                                                'uma_status',
                                                value,
                                            )
                                        }
                                        onStatusChange={(status) =>
                                            form.setData(
                                                'uma_status',
                                                coherentStatus(
                                                    form.data.uma_value,
                                                    status,
                                                ),
                                            )
                                        }
                                    />
                                    <AnnualField
                                        id="fuel_price_per_liter"
                                        label="Combustible por litro"
                                        value={form.data.fuel_price_per_liter}
                                        status={form.data.fuel_price_status}
                                        valueError={
                                            form.errors.fuel_price_per_liter
                                        }
                                        statusError={
                                            form.errors.fuel_price_status
                                        }
                                        onValueChange={(value) =>
                                            setAnnualValue(
                                                'fuel_price_per_liter',
                                                'fuel_price_status',
                                                value,
                                            )
                                        }
                                        onStatusChange={(status) =>
                                            form.setData(
                                                'fuel_price_status',
                                                coherentStatus(
                                                    form.data
                                                        .fuel_price_per_liter,
                                                    status,
                                                ),
                                            )
                                        }
                                    />
                                </CardContent>
                            </Card>
                        </>
                    )}

                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button asChild type="button" variant="outline">
                            <Link href={create()}>Limpiar</Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                form.processing || Boolean(estimatedIncomeError)
                            }
                        >
                            {form.processing
                                ? 'Creando…'
                                : mode === 'copy'
                                  ? 'Copiar presupuesto'
                                  : 'Crear presupuesto'}
                        </Button>
                    </div>
                </form>
            </main>
        </>
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
            />
            <InputError message={error} />
        </div>
    );
}

type AnnualFieldProps = {
    id: string;
    label: string;
    value: string;
    status: AnnualValueStatus;
    valueError?: string;
    statusError?: string;
    onValueChange: (value: string) => void;
    onStatusChange: (status: AnnualValueStatus) => void;
};

function AnnualField({
    id,
    label,
    value,
    status,
    valueError,
    statusError,
    onValueChange,
    onStatusChange,
}: AnnualFieldProps) {
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
                />
                <InputError message={valueError} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`${id}-status`}>Estatus</Label>
                <select
                    id={`${id}-status`}
                    value={status}
                    onChange={(event) =>
                        onStatusChange(event.target.value as AnnualValueStatus)
                    }
                    className="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-xs"
                    aria-invalid={Boolean(statusError)}
                >
                    <option
                        value="pending_review"
                        disabled={value.trim() !== ''}
                    >
                        Pendiente de revisión
                    </option>
                    <option value="provisional">Provisional</option>
                    <option value="final">Final</option>
                </select>
                <InputError message={statusError} />
            </div>
        </fieldset>
    );
}
