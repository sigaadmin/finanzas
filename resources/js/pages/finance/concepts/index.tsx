import { Head, useForm } from '@inertiajs/react';
import { Edit3, Link2, Plus, Save, X } from 'lucide-react';
import { Fragment } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    index as conceptIndex,
    store as conceptStore,
    update as conceptUpdate,
} from '@/routes/finance/charge-concepts';
import { update as officialLinkUpdate } from '@/routes/finance/charge-concepts/official-link';
import { store as officialConceptStore } from '@/routes/finance/official-fee-concepts';

type ChargeConcept = {
    id: number;
    name: string;
    description: string | null;
    amount_pesos: number;
    type: 'internal' | 'external';
    allows_quantity: boolean;
    status: 'active' | 'inactive';
    internal_key: string | null;
    official_link: {
        id: number | null;
        fiscal_year: number;
        status: OfficialLinkStatus;
        label: string;
        official_fee_concept_id: number | null;
        notes: string | null;
    };
    can: {
        update: boolean;
    };
};

type OfficialConcept = {
    id: number;
    code: string;
    name: string;
    amount_pesos: number | null;
    source_name: string;
    published_on: string | null;
    label: string;
};

type Paginated<T> = {
    data: T[];
};

type ConceptFormData = {
    name: string;
    description: string;
    amount: string;
    type: 'internal' | 'external';
    allows_quantity: boolean;
    status: 'active' | 'inactive';
    internal_key: string;
};

type OfficialConceptFormData = {
    fiscal_year: string;
    source_name: string;
    source_url: string;
    published_on: string;
    code: string;
    name: string;
    amount: string;
    notes: string;
};

type OfficialLinkStatus = 'pending_review' | 'linked' | 'not_applicable';

type OfficialLinkFormData = {
    fiscal_year: string;
    status: OfficialLinkStatus;
    official_fee_concept_id: string;
    notes: string;
};

type Props = {
    concepts: Paginated<ChargeConcept>;
    filters: {
        type: string;
        status: string;
        fiscal_year: number;
    };
    options: {
        types: string[];
        statuses: string[];
        official_link_statuses: string[];
    };
    can: {
        create: boolean;
    };
    official: {
        fiscal_year: number;
        concepts: OfficialConcept[];
    };
};

const typeLabels: Record<ChargeConcept['type'], string> = {
    internal: 'Interno',
    external: 'Externo',
};

const statusLabels: Record<ChargeConcept['status'], string> = {
    active: 'Activo',
    inactive: 'Inactivo',
};

const officialLinkLabels: Record<OfficialLinkStatus, string> = {
    pending_review: 'Pendiente de revisión',
    linked: 'Enlazado',
    not_applicable: 'No aplica DOF',
};

const emptyConceptForm: ConceptFormData = {
    name: '',
    description: '',
    amount: '',
    type: 'internal',
    allows_quantity: false,
    status: 'active',
    internal_key: '',
};

function amountFromPesos(amountPesos: number | null): string {
    if (amountPesos === null) {
        return '';
    }

    return String(amountPesos);
}

function payloadFromConceptForm(data: ConceptFormData) {
    return {
        name: data.name,
        description: data.description || null,
        amount_pesos: Math.round(Number(data.amount)),
        type: data.type,
        allows_quantity: data.type === 'internal' && data.allows_quantity,
        status: data.status,
        internal_key: data.internal_key || null,
    };
}

function ConceptFields({
    data,
    errors,
    setData,
    options,
    idPrefix,
    disabled = false,
}: {
    data: ConceptFormData;
    errors: Partial<Record<keyof ConceptFormData | 'amount_pesos', string>>;
    setData: <K extends keyof ConceptFormData>(
        key: K,
        value: ConceptFormData[K],
    ) => void;
    options: Props['options'];
    idPrefix: string;
    disabled?: boolean;
}) {
    const quantityId = `${idPrefix}-allows-quantity`;

    return (
        <div className="grid gap-4 lg:grid-cols-[1.5fr_8rem_8rem_9rem_8rem_10rem]">
            <div className="grid gap-2">
                <Label htmlFor="concept-name">Concepto</Label>
                <Input
                    id="concept-name"
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    disabled={disabled}
                    required
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="concept-amount">Importe</Label>
                <Input
                    id="concept-amount"
                    type="number"
                    min="1"
                    step="1"
                    value={data.amount}
                    onChange={(event) => setData('amount', event.target.value)}
                    disabled={disabled}
                    required
                />
                <InputError message={errors.amount_pesos ?? errors.amount} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="concept-type">Tipo</Label>
                <select
                    id="concept-type"
                    className="h-9 rounded-md border bg-background px-3 text-sm"
                    value={data.type}
                    onChange={(event) => {
                        const type = event.target
                            .value as ChargeConcept['type'];
                        setData(
                            'type',
                            type,
                        );

                        if (type === 'external') {
                            setData('allows_quantity', false);
                        }
                    }}
                    disabled={disabled}
                    required
                >
                    {options.types.map((type) => (
                        <option key={type} value={type}>
                            {typeLabels[type as ChargeConcept['type']] ?? type}
                        </option>
                    ))}
                </select>
                <InputError message={errors.type} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={quantityId}>Cantidad</Label>
                <div className="flex h-9 items-center gap-2 rounded-md border px-3 text-sm">
                    <input
                        id={quantityId}
                        type="checkbox"
                        className="size-4 rounded border-input"
                        checked={data.allows_quantity === true}
                        onChange={(event) =>
                            setData('allows_quantity', event.target.checked)
                        }
                        disabled={disabled || data.type === 'external'}
                    />
                    <Label
                        htmlFor={quantityId}
                        className="cursor-pointer font-normal"
                    >
                        Permitir varias
                    </Label>
                </div>
                <InputError message={errors.allows_quantity} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="concept-status">Estado</Label>
                <select
                    id="concept-status"
                    className="h-9 rounded-md border bg-background px-3 text-sm"
                    value={data.status}
                    onChange={(event) =>
                        setData(
                            'status',
                            event.target.value as ChargeConcept['status'],
                        )
                    }
                    disabled={disabled}
                    required
                >
                    {options.statuses.map((status) => (
                        <option key={status} value={status}>
                            {statusLabels[status as ChargeConcept['status']] ??
                                status}
                        </option>
                    ))}
                </select>
                <InputError message={errors.status} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="concept-key">Clave interna</Label>
                <Input
                    id="concept-key"
                    value={data.internal_key}
                    onChange={(event) =>
                        setData('internal_key', event.target.value)
                    }
                    disabled={disabled}
                    placeholder="CREN/SEQ"
                />
                <InputError message={errors.internal_key} />
            </div>

            <div className="grid gap-2 lg:col-span-6">
                <Label htmlFor="concept-description">Descripción</Label>
                <textarea
                    id="concept-description"
                    className="min-h-20 rounded-md border bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    value={data.description}
                    onChange={(event) =>
                        setData('description', event.target.value)
                    }
                    disabled={disabled}
                />
                <InputError message={errors.description} />
            </div>
        </div>
    );
}

function CreateConceptForm({
    options,
    onSuccess,
}: {
    options: Props['options'];
    onSuccess: () => void;
}) {
    const form = useForm<ConceptFormData>(emptyConceptForm);

    const submit = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.transform(payloadFromConceptForm);
        form.post(conceptStore().url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onSuccess();
            },
        });
    };

    return (
        <form className="grid gap-4" onSubmit={submit}>
            <ConceptFields
                data={form.data}
                errors={form.errors}
                setData={form.setData}
                options={options}
                idPrefix="create-concept"
                disabled={form.processing}
            />

            <div className="flex justify-end">
                <Button type="submit" disabled={form.processing}>
                    <Save className="size-4" />
                    Guardar concepto
                </Button>
            </div>
        </form>
    );
}

function OfficialConceptForm({
    fiscalYear,
    onSuccess,
}: {
    fiscalYear: number;
    onSuccess: () => void;
}) {
    const form = useForm<OfficialConceptFormData>({
        fiscal_year: String(fiscalYear),
        source_name: 'Periódico Oficial del Estado de Quintana Roo',
        source_url: '',
        published_on: '',
        code: '',
        name: '',
        amount: '',
        notes: '',
    });

    const submit = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.transform((data) => ({
            fiscal_year: Number(data.fiscal_year),
            source_name: data.source_name,
            source_url: data.source_url || null,
            published_on: data.published_on || null,
            code: data.code,
            name: data.name,
            amount_pesos: data.amount
                ? Math.round(Number(data.amount))
                : null,
            notes: data.notes || null,
        }));
        form.post(officialConceptStore().url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('code', 'name', 'amount', 'notes');
                onSuccess();
            },
        });
    };

    return (
        <form className="grid gap-4" onSubmit={submit}>
            <div className="grid gap-4 lg:grid-cols-[6rem_1fr_9rem_8rem_1.5fr_8rem]">
                <div className="grid gap-2">
                    <Label htmlFor="official-year">Ejercicio</Label>
                    <Input
                        id="official-year"
                        type="number"
                        value={form.data.fiscal_year}
                        onChange={(event) =>
                            form.setData('fiscal_year', event.target.value)
                        }
                        required
                    />
                    <InputError message={form.errors.fiscal_year} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="official-source">Fuente</Label>
                    <Input
                        id="official-source"
                        value={form.data.source_name}
                        onChange={(event) =>
                            form.setData('source_name', event.target.value)
                        }
                        required
                    />
                    <InputError message={form.errors.source_name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="official-published">Publicado</Label>
                    <Input
                        id="official-published"
                        type="date"
                        value={form.data.published_on}
                        onChange={(event) =>
                            form.setData('published_on', event.target.value)
                        }
                    />
                    <InputError message={form.errors.published_on} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="official-code">Clave</Label>
                    <Input
                        id="official-code"
                        value={form.data.code}
                        onChange={(event) =>
                            form.setData('code', event.target.value)
                        }
                        placeholder="16.1.1"
                        required
                    />
                    <InputError message={form.errors.code} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="official-name">Nombre oficial</Label>
                    <Input
                        id="official-name"
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        required
                    />
                    <InputError message={form.errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="official-amount">Importe</Label>
                    <Input
                        id="official-amount"
                        type="number"
                        min="1"
                        step="1"
                        value={form.data.amount}
                        onChange={(event) =>
                            form.setData('amount', event.target.value)
                        }
                    />
                    <InputError
                        message={
                            (
                                form.errors as Partial<
                                    Record<'amount_pesos', string>
                                >
                            ).amount_pesos ?? form.errors.amount
                        }
                    />
                </div>
            </div>

            <div className="flex justify-end">
                <Button type="submit" disabled={form.processing}>
                    <Save className="size-4" />
                    Guardar clave oficial
                </Button>
            </div>
        </form>
    );
}

function EditConceptForm({
    concept,
    options,
    onCancel,
}: {
    concept: ChargeConcept;
    options: Props['options'];
    onCancel: () => void;
}) {
    const form = useForm<ConceptFormData>({
        name: concept.name,
        description: concept.description ?? '',
        amount: amountFromPesos(concept.amount_pesos),
        type: concept.type,
        allows_quantity: concept.allows_quantity,
        status: concept.status,
        internal_key: concept.internal_key ?? '',
    });

    const submit = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.transform(payloadFromConceptForm);
        form.put(conceptUpdate(concept.id).url, {
            preserveScroll: true,
            onSuccess: onCancel,
        });
    };

    return (
        <form className="grid gap-4" onSubmit={submit}>
            <ConceptFields
                data={form.data}
                errors={form.errors}
                setData={form.setData}
                options={options}
                idPrefix={`edit-concept-${concept.id}`}
                disabled={form.processing}
            />

            <div className="flex justify-end gap-2">
                <Button type="button" variant="ghost" onClick={onCancel}>
                    <X className="size-4" />
                    Cancelar
                </Button>
                <Button type="submit" disabled={form.processing}>
                    <Save className="size-4" />
                    Guardar cambios
                </Button>
            </div>
        </form>
    );
}

function OfficialLinkForm({
    concept,
    official,
    onCancel,
}: {
    concept: ChargeConcept;
    official: Props['official'];
    onCancel: () => void;
}) {
    const form = useForm<OfficialLinkFormData>({
        fiscal_year: String(official.fiscal_year),
        status: concept.official_link.status,
        official_fee_concept_id:
            concept.official_link.official_fee_concept_id?.toString() ?? '',
        notes: concept.official_link.notes ?? '',
    });

    const submit = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.transform((data) => ({
            fiscal_year: Number(data.fiscal_year),
            status: data.status,
            official_fee_concept_id:
                data.status === 'linked'
                    ? Number(data.official_fee_concept_id)
                    : null,
            notes: data.notes || null,
        }));
        form.put(officialLinkUpdate(concept.id).url, {
            preserveScroll: true,
            onSuccess: onCancel,
        });
    };

    return (
        <form className="grid gap-4" onSubmit={submit}>
            <div className="grid gap-4 lg:grid-cols-[7rem_12rem_1fr]">
                <div className="grid gap-2">
                    <Label htmlFor={`link-year-${concept.id}`}>
                        Ejercicio
                    </Label>
                    <Input
                        id={`link-year-${concept.id}`}
                        type="number"
                        value={form.data.fiscal_year}
                        onChange={(event) =>
                            form.setData('fiscal_year', event.target.value)
                        }
                        required
                    />
                    <InputError message={form.errors.fiscal_year} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`link-status-${concept.id}`}>
                        Estado
                    </Label>
                    <select
                        id={`link-status-${concept.id}`}
                        className="h-9 rounded-md border bg-background px-3 text-sm"
                        value={form.data.status}
                        onChange={(event) =>
                            form.setData(
                                'status',
                                event.target.value as OfficialLinkStatus,
                            )
                        }
                    >
                        {Object.entries(officialLinkLabels).map(
                            ([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ),
                        )}
                    </select>
                    <InputError message={form.errors.status} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor={`link-official-${concept.id}`}>
                        Concepto oficial
                    </Label>
                    <select
                        id={`link-official-${concept.id}`}
                        className="h-9 rounded-md border bg-background px-3 text-sm"
                        value={form.data.official_fee_concept_id}
                        onChange={(event) =>
                            form.setData(
                                'official_fee_concept_id',
                                event.target.value,
                            )
                        }
                        disabled={form.data.status !== 'linked'}
                    >
                        <option value="">Seleccionar concepto oficial</option>
                        {official.concepts.map((officialConcept) => (
                            <option
                                key={officialConcept.id}
                                value={officialConcept.id}
                            >
                                {officialConcept.label}
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.official_fee_concept_id} />
                </div>

                <div className="grid gap-2 lg:col-span-3">
                    <Label htmlFor={`link-notes-${concept.id}`}>Notas</Label>
                    <Input
                        id={`link-notes-${concept.id}`}
                        value={form.data.notes}
                        onChange={(event) =>
                            form.setData('notes', event.target.value)
                        }
                        placeholder="Fundamento, observación o motivo de no aplicación"
                    />
                    <InputError message={form.errors.notes} />
                </div>
            </div>

            <div className="flex justify-end gap-2">
                <Button type="button" variant="ghost" onClick={onCancel}>
                    <X className="size-4" />
                    Cancelar
                </Button>
                <Button type="submit" disabled={form.processing}>
                    <Save className="size-4" />
                    Guardar vínculo
                </Button>
            </div>
        </form>
    );
}

export default function ChargeConceptIndex({
    concepts,
    filters,
    options,
    can,
    official,
}: Props) {
    const [createConceptOpen, setCreateConceptOpen] =
        useState<boolean>(false);
    const [officialConceptOpen, setOfficialConceptOpen] =
        useState<boolean>(false);
    const [editingConceptId, setEditingConceptId] = useState<number | null>(
        null,
    );
    const [linkingConceptId, setLinkingConceptId] = useState<number | null>(
        null,
    );
    const editingConcept =
        concepts.data.find((concept) => concept.id === editingConceptId) ??
        null;
    const linkingConcept =
        concepts.data.find((concept) => concept.id === linkingConceptId) ??
        null;

    return (
        <>
            <Head title="Conceptos de cobro" />
            <main className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-xl font-semibold">
                            Conceptos de cobro
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Catálogo financiero institucional y vínculos
                            oficiales por ejercicio.
                        </p>
                    </div>
                    {can.create && (
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                onClick={() => setCreateConceptOpen(true)}
                            >
                                <Plus className="size-4" />
                                Nuevo concepto
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOfficialConceptOpen(true)}
                            >
                                <Plus className="size-4" />
                                Agregar clave oficial
                            </Button>
                        </div>
                    )}
                </header>

                <form
                    className="flex flex-wrap items-end gap-3 rounded-lg border p-4"
                    action={conceptIndex().url}
                    method="get"
                >
                    <label className="grid gap-1 text-sm">
                        <span className="text-muted-foreground">Ejercicio</span>
                        <Input
                            name="fiscal_year"
                            type="number"
                            defaultValue={filters.fiscal_year}
                            className="w-28"
                        />
                    </label>

                    <label className="grid gap-1 text-sm">
                        <span className="text-muted-foreground">Tipo</span>
                        <select
                            name="type"
                            defaultValue={filters.type}
                            className="h-9 rounded-md border bg-background px-3"
                        >
                            <option value="">Todos</option>
                            {options.types.map((type) => (
                                <option key={type} value={type}>
                                    {typeLabels[type as ChargeConcept['type']] ??
                                        type}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="grid gap-1 text-sm">
                        <span className="text-muted-foreground">Estado</span>
                        <select
                            name="status"
                            defaultValue={filters.status}
                            className="h-9 rounded-md border bg-background px-3"
                        >
                            <option value="">Todos</option>
                            {options.statuses.map((status) => (
                                <option key={status} value={status}>
                                    {statusLabels[
                                        status as ChargeConcept['status']
                                    ] ?? status}
                                </option>
                            ))}
                        </select>
                    </label>

                    <Button type="submit" variant="outline">
                        Filtrar
                    </Button>
                </form>

                <section className="overflow-hidden rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted text-left">
                            <tr>
                                <th className="px-3 py-2">Concepto</th>
                                <th className="px-3 py-2">Tipo</th>
                                <th className="px-3 py-2">Cantidad</th>
                                <th className="px-3 py-2">Estado</th>
                                <th className="px-3 py-2">Clave oficial</th>
                                <th className="px-3 py-2 text-right">
                                    Importe
                                </th>
                                <th className="w-48 px-3 py-2 text-right">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {concepts.data.map((concept) => (
                                <Fragment key={concept.id}>
                                    <tr className="border-t">
                                        <td className="px-3 py-2">
                                            <div className="font-medium">
                                                {concept.name}
                                            </div>
                                            {concept.internal_key && (
                                                <div className="text-xs text-muted-foreground">
                                                    {concept.internal_key}
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            {typeLabels[concept.type]}
                                        </td>
                                        <td className="px-3 py-2">
                                            {concept.allows_quantity
                                                ? 'Variable'
                                                : 'Una vez'}
                                        </td>
                                        <td className="px-3 py-2">
                                            {statusLabels[concept.status]}
                                        </td>
                                        <td className="px-3 py-2">
                                            <div className="font-medium">
                                                {concept.official_link.label}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {officialLinkLabels[
                                                    concept.official_link.status
                                                ] ?? concept.official_link.status}
                                            </div>
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            $
                                            {amountFromPesos(
                                                concept.amount_pesos,
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            {concept.can.update && (
                                                <div className="flex justify-end gap-1">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => {
                                                            setEditingConceptId(
                                                                concept.id,
                                                            );
                                                            setLinkingConceptId(
                                                                null,
                                                            );
                                                        }}
                                                    >
                                                        <Edit3 className="size-4" />
                                                        Editar
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => {
                                                            setLinkingConceptId(
                                                                concept.id,
                                                            );
                                                            setEditingConceptId(
                                                                null,
                                                            );
                                                        }}
                                                    >
                                                        <Link2 className="size-4" />
                                                        Relacionar
                                                    </Button>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                </Fragment>
                            ))}
                        </tbody>
                    </table>
                </section>

                <section className="overflow-hidden rounded-lg border">
                    <div className="border-b px-4 py-3">
                        <h2 className="text-sm font-semibold">
                            Conceptos oficiales {official.fiscal_year}
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Revisa las claves y montos publicados para este
                            año.
                        </p>
                    </div>
                    <table className="w-full text-sm">
                        <thead className="bg-muted text-left">
                            <tr>
                                <th className="px-3 py-2">Clave</th>
                                <th className="px-3 py-2">Nombre oficial</th>
                                <th className="px-3 py-2">Fuente</th>
                                <th className="px-3 py-2">Publicado</th>
                                <th className="px-3 py-2 text-right">
                                    Importe
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {official.concepts.length > 0 ? (
                                official.concepts.map((concept) => (
                                    <tr key={concept.id} className="border-t">
                                        <td className="px-3 py-2 font-medium">
                                            {concept.code}
                                        </td>
                                        <td className="px-3 py-2">
                                            {concept.name}
                                        </td>
                                        <td className="px-3 py-2">
                                            {concept.source_name}
                                        </td>
                                        <td className="px-3 py-2">
                                            {concept.published_on ??
                                                'Sin fecha'}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            {concept.amount_pesos === null
                                                ? 'Sin importe'
                                                : `$${amountFromPesos(concept.amount_pesos)}`}
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr className="border-t">
                                    <td
                                        colSpan={5}
                                        className="px-3 py-6 text-center text-muted-foreground"
                                    >
                                        No hay conceptos oficiales capturados
                                        para este ejercicio.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </section>

                <Dialog
                    open={createConceptOpen}
                    onOpenChange={setCreateConceptOpen}
                >
                    <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-5xl">
                        <DialogHeader>
                            <DialogTitle>Nuevo concepto de cobro</DialogTitle>
                            <DialogDescription>
                                Agrega un servicio o pago que podrá seleccionarse
                                al iniciar un trámite.
                            </DialogDescription>
                        </DialogHeader>
                        <CreateConceptForm
                            options={options}
                            onSuccess={() => setCreateConceptOpen(false)}
                        />
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={officialConceptOpen}
                    onOpenChange={setOfficialConceptOpen}
                >
                    <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-6xl">
                        <DialogHeader>
                            <DialogTitle>Agregar clave oficial</DialogTitle>
                            <DialogDescription>
                                Registra la clave, nombre e importe oficial del
                                ejercicio seleccionado.
                            </DialogDescription>
                        </DialogHeader>
                        <OfficialConceptForm
                            fiscalYear={official.fiscal_year}
                            onSuccess={() => setOfficialConceptOpen(false)}
                        />
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={editingConcept !== null}
                    onOpenChange={(open) =>
                        !open && setEditingConceptId(null)
                    }
                >
                    <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-5xl">
                        <DialogHeader>
                            <DialogTitle>Editar concepto de cobro</DialogTitle>
                            <DialogDescription>
                                Ajusta el nombre, monto o clasificación que verá
                                la oficina de Finanzas.
                            </DialogDescription>
                        </DialogHeader>
                        {editingConcept && (
                            <EditConceptForm
                                concept={editingConcept}
                                options={options}
                                onCancel={() => setEditingConceptId(null)}
                            />
                        )}
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={linkingConcept !== null}
                    onOpenChange={(open) =>
                        !open && setLinkingConceptId(null)
                    }
                >
                    <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-5xl">
                        <DialogHeader>
                            <DialogTitle>Relacionar con DOF</DialogTitle>
                            <DialogDescription>
                                Enlaza el concepto al catálogo oficial anual o
                                márcalo como no aplicable.
                            </DialogDescription>
                        </DialogHeader>
                        {linkingConcept && (
                            <OfficialLinkForm
                                concept={linkingConcept}
                                official={official}
                                onCancel={() => setLinkingConceptId(null)}
                            />
                        )}
                    </DialogContent>
                </Dialog>
            </main>
        </>
    );
}
