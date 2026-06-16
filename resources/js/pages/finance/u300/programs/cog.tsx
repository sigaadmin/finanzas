import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle, Plus, Save, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import finance from '@/routes/finance';

type Classification = {
    id: number;
    specific_item_code: string;
    specific_item_name: string;
};

type BudgetLine = {
    id: number;
    amount_cents: number;
    description: string | null;
    expense_classification_code: string | null;
    expense_classification_name: string | null;
    exercise_month: string | null;
};

type BudgetAction = {
    id: number;
    number: string;
    name: string;
    justification: string | null;
    goal_number: string;
    adjusted_total_cents: number;
    lines: BudgetLine[];
};

type Props = {
    program: {
        id: number;
        fiscal_year: number;
        name: string;
        actions: BudgetAction[];
    };
    classifications: Classification[];
};

type CogLine = {
    row_key: string;
    id: number | null;
    u300_action_id: number;
    amount_cents: number;
    expense_classification_code: string | null;
    exercise_month: string | null;
};

type CogAction = {
    id: number;
    justification: string | null;
};

type CogFormData = {
    actions: CogAction[];
    lines: CogLine[];
};

const months = ['AGO', 'SEP', 'OCT', 'NOV', 'DIC'];

function money(cents: number): string {
    return (cents / 100).toLocaleString('es-MX', {
        style: 'currency',
        currency: 'MXN',
    });
}

function centsFromPesos(value: string): number {
    if (value.trim() === '') {
        return 0;
    }

    return Math.round(Number(value) * 100);
}

function pesosFromCents(value: number): string {
    return value === 0 ? '' : String(value / 100);
}

function newRowKey(): string {
    return `new-${Date.now()}-${Math.random()}`;
}

export default function U300CogConversion({ program, classifications }: Props) {
    const form = useForm<CogFormData>({
        actions: program.actions.map((action) => ({
            id: action.id,
            justification: action.justification,
        })),
        lines: program.actions.flatMap((action) =>
            action.lines.map((line) => ({
                row_key: `line-${line.id}`,
                id: line.id,
                u300_action_id: action.id,
                amount_cents: line.amount_cents,
                expense_classification_code: line.expense_classification_code,
                exercise_month: line.exercise_month,
            })),
        ),
    });

    const classificationsByCode = new Map(
        classifications.map((classification) => [
            classification.specific_item_code,
            classification,
        ]),
    );

    function linesForAction(actionId: number): CogLine[] {
        return form.data.lines.filter(
            (line) => line.u300_action_id === actionId,
        );
    }

    function actionLineTotal(actionId: number): number {
        return linesForAction(actionId).reduce(
            (total, line) => total + line.amount_cents,
            0,
        );
    }

    function updateLine(rowKey: string, updates: Partial<CogLine>): void {
        form.setData(
            'lines',
            form.data.lines.map((line) =>
                line.row_key === rowKey ? { ...line, ...updates } : line,
            ),
        );
    }

    function updateAction(actionId: number, updates: Partial<CogAction>): void {
        form.setData(
            'actions',
            form.data.actions.map((action) =>
                action.id === actionId ? { ...action, ...updates } : action,
            ),
        );
    }

    function actionJustification(actionId: number): string {
        return (
            form.data.actions.find((action) => action.id === actionId)
                ?.justification ?? ''
        );
    }

    function addLine(action: BudgetAction): void {
        form.setData('lines', [
            ...form.data.lines,
            {
                row_key: newRowKey(),
                id: null,
                u300_action_id: action.id,
                amount_cents: 0,
                expense_classification_code: null,
                exercise_month: null,
            },
        ]);
    }

    function removeLine(rowKey: string): void {
        form.setData(
            'lines',
            form.data.lines.filter((line) => line.row_key !== rowKey),
        );
    }

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.put(finance.u300.programs.cog.update(program).url);
    }

    return (
        <>
            <Head title="Conversión COG U300" />
            <main className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <header className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Presupuesto U300 · {program.fiscal_year}
                        </p>
                        <h1 className="text-xl font-semibold">
                            Conversión a COG
                        </h1>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href={finance.u300.programs.show(program)}>
                                Volver
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link
                                href={finance.expenseClassifications.imports.create()}
                            >
                                Importar COG
                            </Link>
                        </Button>
                        <Button
                            disabled={form.processing}
                            form="u300-cog-form"
                            type="submit"
                        >
                            {form.processing ? (
                                <LoaderCircle className="size-4 animate-spin" />
                            ) : (
                                <Save className="size-4" />
                            )}
                            Guardar
                        </Button>
                    </div>
                </header>

                <form id="u300-cog-form" onSubmit={submit}>
                    <datalist id="u300-cog-classifications">
                        {classifications.map((classification) => (
                            <option
                                key={classification.id}
                                value={classification.specific_item_code}
                            >
                                {classification.specific_item_name}
                            </option>
                        ))}
                    </datalist>

                    <div className="grid gap-4">
                        {program.actions.map((action) => {
                            const actionLines = linesForAction(action.id);
                            const lineTotal = actionLineTotal(action.id);
                            const totalMismatch =
                                lineTotal !== action.adjusted_total_cents;

                            return (
                                <section
                                    key={action.id}
                                    className="overflow-hidden rounded-lg border"
                                >
                                    <div className="flex flex-col gap-2 border-b bg-muted/50 px-4 py-3 lg:flex-row lg:items-start lg:justify-between">
                                        <div className="min-w-0">
                                            <p className="text-xs font-medium text-muted-foreground">
                                                Meta {action.goal_number} ·
                                                Acción {action.number}
                                            </p>
                                            <h2 className="mt-1 text-sm leading-6 font-semibold">
                                                {action.name}
                                            </h2>
                                        </div>
                                        <div className="flex flex-wrap items-end gap-4">
                                            <div>
                                                <p className="text-xs text-muted-foreground">
                                                    Adecuado
                                                </p>
                                                <p className="text-sm font-semibold">
                                                    {money(
                                                        action.adjusted_total_cents,
                                                    )}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs text-muted-foreground">
                                                    Partidas
                                                </p>
                                                <p
                                                    className={
                                                        totalMismatch
                                                            ? 'text-sm font-semibold text-destructive'
                                                            : 'text-sm font-semibold'
                                                    }
                                                >
                                                    {money(lineTotal)}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs text-muted-foreground">
                                                    Disponible
                                                </p>
                                                <p
                                                    className={
                                                        totalMismatch
                                                            ? 'text-sm font-semibold text-destructive'
                                                            : 'text-sm font-semibold'
                                                    }
                                                >
                                                    {money(
                                                        action.adjusted_total_cents -
                                                            lineTotal,
                                                    )}
                                                </p>
                                            </div>
                                            <Button
                                                size="sm"
                                                type="button"
                                                variant="outline"
                                                onClick={() => addLine(action)}
                                            >
                                                <Plus className="size-4" />
                                                Agregar partida
                                            </Button>
                                        </div>
                                    </div>

                                    <div className="border-b px-4 py-3">
                                        <label className="grid gap-2">
                                            <span className="text-xs font-medium text-muted-foreground">
                                                Justificación de la acción
                                            </span>
                                            <textarea
                                                className="min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                value={actionJustification(
                                                    action.id,
                                                )}
                                                onChange={(event) =>
                                                    updateAction(action.id, {
                                                        justification:
                                                            event.target
                                                                .value || null,
                                                    })
                                                }
                                            />
                                        </label>
                                    </div>

                                    <div className="overflow-x-auto">
                                        <table className="min-w-[1060px] text-sm">
                                            <thead className="bg-muted/60 text-left">
                                                <tr>
                                                    <th className="px-3 py-2">
                                                        Partida
                                                    </th>
                                                    <th className="px-3 py-2">
                                                        Mes
                                                    </th>
                                                    <th className="px-3 py-2">
                                                        Monto
                                                    </th>
                                                    <th className="px-3 py-2 text-right">
                                                        Acción
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {actionLines.map((line) => {
                                                    const classification =
                                                        line.expense_classification_code
                                                            ? classificationsByCode.get(
                                                                  line.expense_classification_code,
                                                              )
                                                            : undefined;

                                                    return (
                                                        <tr
                                                            key={line.row_key}
                                                            className="border-t"
                                                        >
                                                            <td className="min-w-64 px-3 py-2 align-top">
                                                                <Input
                                                                    list="u300-cog-classifications"
                                                                    placeholder="37501"
                                                                    value={
                                                                        line.expense_classification_code ??
                                                                        ''
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        updateLine(
                                                                            line.row_key,
                                                                            {
                                                                                expense_classification_code:
                                                                                    event
                                                                                        .target
                                                                                        .value ||
                                                                                    null,
                                                                            },
                                                                        )
                                                                    }
                                                                />
                                                                <p className="mt-1 text-xs text-muted-foreground">
                                                                    {classification
                                                                        ? classification.specific_item_name
                                                                        : 'Sin partida seleccionada'}
                                                                </p>
                                                            </td>
                                                            <td className="w-32 px-3 py-2 align-top">
                                                                <Select
                                                                    value={
                                                                        line.exercise_month ??
                                                                        ''
                                                                    }
                                                                    onValueChange={(
                                                                        value,
                                                                    ) =>
                                                                        updateLine(
                                                                            line.row_key,
                                                                            {
                                                                                exercise_month:
                                                                                    value,
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    <SelectTrigger>
                                                                        <SelectValue placeholder="Mes" />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        {months.map(
                                                                            (
                                                                                month,
                                                                            ) => (
                                                                                <SelectItem
                                                                                    key={
                                                                                        month
                                                                                    }
                                                                                    value={
                                                                                        month
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        month
                                                                                    }
                                                                                </SelectItem>
                                                                            ),
                                                                        )}
                                                                    </SelectContent>
                                                                </Select>
                                                            </td>
                                                            <td className="w-40 px-3 py-2 align-top">
                                                                <Input
                                                                    min={0}
                                                                    step="0.01"
                                                                    type="number"
                                                                    value={pesosFromCents(
                                                                        line.amount_cents,
                                                                    )}
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        updateLine(
                                                                            line.row_key,
                                                                            {
                                                                                amount_cents:
                                                                                    centsFromPesos(
                                                                                        event
                                                                                            .target
                                                                                            .value,
                                                                                    ),
                                                                            },
                                                                        )
                                                                    }
                                                                />
                                                            </td>
                                                            <td className="px-3 py-2 text-right align-top">
                                                                <Button
                                                                    disabled={
                                                                        actionLines.length ===
                                                                        1
                                                                    }
                                                                    size="icon"
                                                                    type="button"
                                                                    variant="ghost"
                                                                    onClick={() =>
                                                                        removeLine(
                                                                            line.row_key,
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="size-4" />
                                                                </Button>
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                            );
                        })}
                    </div>
                    <InputError message={form.errors.lines} />
                </form>
            </main>
        </>
    );
}
