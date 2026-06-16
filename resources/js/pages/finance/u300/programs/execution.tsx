import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle, Plus, WalletCards, XCircle } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import finance from '@/routes/finance';

type BudgetLine = {
    id: number;
    action_number: string;
    action_name: string;
    cog_code: string | null;
    cog_name: string | null;
    amount_cents: number;
    executed_cents: number;
    available_cents: number;
};

type Movement = {
    id: number;
    line_id: number;
    line_label: string;
    type: string;
    movement_date: string;
    concept: string;
    document_reference: string | null;
    amount_cents: number;
    is_cancelled: boolean;
    cancelled_at: string | null;
    cancellation_reason: string | null;
};

type Props = {
    program: {
        id: number;
        fiscal_year: number;
        name: string;
        lines: BudgetLine[];
        movements: Movement[];
    };
};

type MovementFormData = {
    u300_budget_line_id: number | null;
    type: string;
    movement_date: string;
    concept: string;
    document_reference: string;
    amount_cents: number;
};

type CancellationFormData = {
    cancellation_reason: string;
};

const movementTypes = [
    { value: 'expense', label: 'Ejercido' },
    { value: 'commitment', label: 'Comprometido' },
    { value: 'reimbursement', label: 'Reintegro' },
];

function money(cents: number): string {
    return (cents / 100).toLocaleString('es-MX', {
        style: 'currency',
        currency: 'MXN',
    });
}

function centsFromPesos(value: string): number {
    const normalized = value.replaceAll(',', '').trim();

    return Math.round(Number(normalized || 0) * 100);
}

function movementTypeLabel(type: string): string {
    return (
        movementTypes.find((movementType) => movementType.value === type)
            ?.label ?? type
    );
}

export default function U300BudgetExecution({ program }: Props) {
    const form = useForm<MovementFormData>({
        u300_budget_line_id: program.lines[0]?.id ?? null,
        type: 'expense',
        movement_date: new Date().toISOString().slice(0, 10),
        concept: '',
        document_reference: '',
        amount_cents: 0,
    });
    const cancellationForm = useForm<CancellationFormData>({
        cancellation_reason: '',
    });

    const assignedTotal = program.lines.reduce(
        (total, line) => total + line.amount_cents,
        0,
    );
    const executedTotal = program.lines.reduce(
        (total, line) => total + line.executed_cents,
        0,
    );
    const availableTotal = program.lines.reduce(
        (total, line) => total + line.available_cents,
        0,
    );

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.post(finance.u300.programs.execution.store(program).url, {
            onSuccess: () =>
                form.reset('concept', 'document_reference', 'amount_cents'),
        });
    }

    function cancelMovement(movement: Movement): void {
        const reason = window.prompt('Motivo de cancelación');

        if (!reason?.trim()) {
            return;
        }

        cancellationForm.transform(() => ({
            cancellation_reason: reason.trim(),
        }));
        cancellationForm.patch(
            finance.u300.programs.execution.cancel({
                program,
                movement,
            }).url,
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Ejercicio U300" />
            <main className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <header className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Presupuesto U300 · {program.fiscal_year}
                        </p>
                        <h1 className="text-xl font-semibold">
                            Ejercicio presupuestal
                        </h1>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href={finance.u300.programs.show(program)}>
                                Volver
                            </Link>
                        </Button>
                        <Button
                            disabled={form.processing}
                            form="u300-budget-movement-form"
                            type="submit"
                        >
                            {form.processing ? (
                                <LoaderCircle className="size-4 animate-spin" />
                            ) : (
                                <Plus className="size-4" />
                            )}
                            Registrar
                        </Button>
                    </div>
                </header>

                <section className="grid gap-3 md:grid-cols-3">
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">
                            Asignado
                        </p>
                        <p className="mt-2 text-xl font-semibold">
                            {money(assignedTotal)}
                        </p>
                    </div>
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">
                            Ejercido / comprometido
                        </p>
                        <p className="mt-2 text-xl font-semibold">
                            {money(executedTotal)}
                        </p>
                    </div>
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">
                            Disponible
                        </p>
                        <p className="mt-2 text-xl font-semibold">
                            {money(availableTotal)}
                        </p>
                    </div>
                </section>

                <form
                    id="u300-budget-movement-form"
                    className="grid gap-4 rounded-lg border p-4"
                    onSubmit={submit}
                >
                    <div className="flex items-center gap-2">
                        <WalletCards className="size-4 text-muted-foreground" />
                        <h2 className="text-sm font-semibold">
                            Nuevo movimiento
                        </h2>
                    </div>
                    <div className="grid gap-4 lg:grid-cols-6">
                        <div className="grid gap-2 lg:col-span-2">
                            <Label>Partida</Label>
                            <Select
                                value={
                                    form.data.u300_budget_line_id
                                        ? String(
                                              form.data.u300_budget_line_id,
                                          )
                                        : ''
                                }
                                onValueChange={(value) =>
                                    form.setData(
                                        'u300_budget_line_id',
                                        Number(value),
                                    )
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccionar partida" />
                                </SelectTrigger>
                                <SelectContent>
                                    {program.lines.map((line) => (
                                        <SelectItem
                                            key={line.id}
                                            value={String(line.id)}
                                        >
                                            {line.cog_code ?? 'Sin COG'} ·{' '}
                                            {money(line.available_cents)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError
                                message={form.errors.u300_budget_line_id}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Tipo</Label>
                            <Select
                                value={form.data.type}
                                onValueChange={(value) =>
                                    form.setData('type', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {movementTypes.map((type) => (
                                        <SelectItem
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.type} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Fecha</Label>
                            <Input
                                type="date"
                                value={form.data.movement_date}
                                onChange={(event) =>
                                    form.setData(
                                        'movement_date',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.movement_date} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Monto</Label>
                            <Input
                                inputMode="decimal"
                                value={
                                    form.data.amount_cents
                                        ? form.data.amount_cents / 100
                                        : ''
                                }
                                onChange={(event) =>
                                    form.setData(
                                        'amount_cents',
                                        centsFromPesos(event.target.value),
                                    )
                                }
                            />
                            <InputError message={form.errors.amount_cents} />
                        </div>
                        <div className="grid gap-2">
                            <Label>Referencia</Label>
                            <Input
                                value={form.data.document_reference}
                                onChange={(event) =>
                                    form.setData(
                                        'document_reference',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={form.errors.document_reference}
                            />
                        </div>
                        <div className="grid gap-2 lg:col-span-6">
                            <Label>Concepto</Label>
                            <Input
                                value={form.data.concept}
                                onChange={(event) =>
                                    form.setData('concept', event.target.value)
                                }
                            />
                            <InputError message={form.errors.concept} />
                        </div>
                    </div>
                </form>

                <section className="overflow-hidden rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2">Partida</th>
                                <th className="px-3 py-2 text-right">
                                    Asignado
                                </th>
                                <th className="px-3 py-2 text-right">
                                    Ejercido
                                </th>
                                <th className="px-3 py-2 text-right">
                                    Disponible
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {program.lines.map((line) => (
                                <tr key={line.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <span className="font-medium">
                                            {line.action_number}
                                        </span>{' '}
                                        {line.action_name}
                                        <p className="text-xs text-muted-foreground">
                                            {line.cog_code} · {line.cog_name}
                                        </p>
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {money(line.amount_cents)}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {money(line.executed_cents)}
                                    </td>
                                    <td className="px-3 py-2 text-right font-medium">
                                        {money(line.available_cents)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>

                <section className="overflow-hidden rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2">Fecha</th>
                                <th className="px-3 py-2">Tipo</th>
                                <th className="px-3 py-2">Concepto</th>
                                <th className="px-3 py-2">Referencia</th>
                                <th className="px-3 py-2 text-right">
                                    Monto
                                </th>
                                <th className="px-3 py-2 text-right">
                                    Acción
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {program.movements.map((movement) => (
                                <tr
                                    key={movement.id}
                                    className={
                                        movement.is_cancelled
                                            ? 'border-t bg-muted/30 text-muted-foreground'
                                            : 'border-t'
                                    }
                                >
                                    <td className="px-3 py-2">
                                        {movement.movement_date}
                                    </td>
                                    <td className="px-3 py-2">
                                        {movementTypeLabel(movement.type)}
                                        {movement.is_cancelled && (
                                            <p className="text-xs">
                                                Cancelado
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-3 py-2">
                                        {movement.concept}
                                        <p className="text-xs text-muted-foreground">
                                            {movement.line_label}
                                        </p>
                                        {movement.cancellation_reason && (
                                            <p className="text-xs text-muted-foreground">
                                                {movement.cancellation_reason}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-3 py-2">
                                        {movement.document_reference}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {money(movement.amount_cents)}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {!movement.is_cancelled && (
                                            <Button
                                                disabled={
                                                    cancellationForm.processing
                                                }
                                                size="sm"
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    cancelMovement(movement)
                                                }
                                            >
                                                <XCircle className="size-4" />
                                                Cancelar
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {program.movements.length === 0 && (
                                <tr>
                                    <td
                                        className="px-3 py-8 text-center text-muted-foreground"
                                        colSpan={6}
                                    >
                                        Sin movimientos registrados
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </section>
            </main>
        </>
    );
}
