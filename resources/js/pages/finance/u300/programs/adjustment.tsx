import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle, Save } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import finance from '@/routes/finance';

type U300Action = {
    id: number;
    number: string;
    name: string;
    approved_total_cents: number | null;
    adjusted_amount_cents: number;
};

type Goal = {
    id: number;
    number: string;
    description: string;
    approved_total_cents: number | null;
    actions: U300Action[];
};

type Project = {
    id: number;
    number: string;
    name: string;
    goals: Goal[];
};

type Props = {
    program: {
        id: number;
        fiscal_year: number;
        name: string;
        approved_total_cents: number | null;
        federal_authorized_total_cents: number | null;
        adjustment_limit_cents: number | null;
        adjusted_total_cents: number;
        projects: Project[];
    };
};

type AdjustmentFormData = {
    allocations: Array<{
        u300_action_id: number;
        amount_cents: number;
    }>;
};

function money(cents: number | null): string {
    return ((cents ?? 0) / 100).toLocaleString('es-MX', {
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

export default function U300Adjustment({ program }: Props) {
    const form = useForm<AdjustmentFormData>({
        allocations: program.projects.flatMap((project) =>
            project.goals.flatMap((goal) =>
                goal.actions.map((action) => ({
                    u300_action_id: action.id,
                    amount_cents: action.adjusted_amount_cents,
                })),
            ),
        ),
    });

    const adjustedTotal = form.data.allocations.reduce(
        (total, allocation) => total + allocation.amount_cents,
        0,
    );
    const adjustmentLimit = program.adjustment_limit_cents ?? 0;
    const adjustmentExceeded = adjustedTotal > adjustmentLimit;

    function allocationFor(actionId: number) {
        return form.data.allocations.find(
            (allocation) => allocation.u300_action_id === actionId,
        );
    }

    function updateAmount(actionId: number, value: string): void {
        form.setData(
            'allocations',
            form.data.allocations.map((allocation) =>
                allocation.u300_action_id === actionId
                    ? { ...allocation, amount_cents: centsFromPesos(value) }
                    : allocation,
            ),
        );
    }

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.put(finance.u300.programs.adjustment.update(program).url);
    }

    return (
        <>
            <Head title="Adecuación U300" />
            <main className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <header className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Presupuesto U300 · {program.fiscal_year}
                        </p>
                        <h1 className="text-xl font-semibold">
                            Adecuación presupuestal
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
                            form="u300-adjustment-form"
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

                <section className="grid gap-3 md:grid-cols-4">
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">
                            Dictaminado
                        </p>
                        <p className="mt-2 text-xl font-semibold">
                            {money(program.approved_total_cents)}
                        </p>
                    </div>
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">
                            Límite de adecuación
                        </p>
                        <p className="mt-2 text-xl font-semibold">
                            {money(program.adjustment_limit_cents)}
                        </p>
                    </div>
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">
                            Adecuado
                        </p>
                        <p
                            className={
                                adjustmentExceeded
                                    ? 'mt-2 text-xl font-semibold text-destructive'
                                    : 'mt-2 text-xl font-semibold'
                            }
                        >
                            {money(adjustedTotal)}
                        </p>
                    </div>
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">
                            Pendiente por adecuar
                        </p>
                        <p
                            className={
                                adjustmentExceeded
                                    ? 'mt-2 text-xl font-semibold text-destructive'
                                    : 'mt-2 text-xl font-semibold'
                            }
                        >
                            {money(adjustmentLimit - adjustedTotal)}
                        </p>
                    </div>
                </section>

                <form id="u300-adjustment-form" onSubmit={submit}>
                    <div className="grid gap-4">
                        {program.projects.map((project) => (
                            <section
                                key={project.id}
                                className="rounded-lg border"
                            >
                                <div className="border-b bg-muted/50 px-4 py-3">
                                    <h2 className="text-sm font-semibold">
                                        {project.number}. {project.name}
                                    </h2>
                                </div>
                                <div className="grid gap-4 p-4">
                                    {project.goals.map((goal) => {
                                        const goalActionIds = goal.actions.map(
                                            (action) => action.id,
                                        );
                                        const goalAdjusted =
                                            form.data.allocations
                                                .filter((allocation) =>
                                                    goalActionIds.includes(
                                                        allocation.u300_action_id,
                                                    ),
                                                )
                                                .reduce(
                                                    (total, allocation) =>
                                                        total +
                                                        allocation.amount_cents,
                                                    0,
                                                );
                                        const goalExceeded =
                                            goalAdjusted >
                                            (goal.approved_total_cents ?? 0);

                                        return (
                                            <article
                                                key={goal.id}
                                                className="grid gap-3"
                                            >
                                                <div className="flex flex-col gap-1 md:flex-row md:items-start md:justify-between">
                                                    <div>
                                                        <h3 className="text-sm font-semibold">
                                                            {goal.number}{' '}
                                                            {goal.description}
                                                        </h3>
                                                        <p
                                                            className={
                                                                goalExceeded
                                                                    ? 'text-sm text-destructive'
                                                                    : 'text-sm text-muted-foreground'
                                                            }
                                                        >
                                                            Adecuado:{' '}
                                                            {money(
                                                                goalAdjusted,
                                                            )}{' '}
                                                            / Bolsa:{' '}
                                                            {money(
                                                                goal.approved_total_cents,
                                                            )}
                                                        </p>
                                                    </div>
                                                </div>

                                                <div className="overflow-hidden rounded-lg border">
                                                    <table className="w-full text-sm">
                                                        <thead className="bg-muted/60 text-left">
                                                            <tr>
                                                                <th className="px-3 py-2">
                                                                    Acción
                                                                </th>
                                                                <th className="px-3 py-2 text-right">
                                                                    Aprobado
                                                                    directo
                                                                </th>
                                                                <th className="px-3 py-2 text-right">
                                                                    Adecuado
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            {goal.actions.map(
                                                                (action) => {
                                                                    const allocation =
                                                                        allocationFor(
                                                                            action.id,
                                                                        );

                                                                    return (
                                                                        <tr
                                                                            key={
                                                                                action.id
                                                                            }
                                                                            className="border-t"
                                                                        >
                                                                            <td className="max-w-96 px-3 py-2 align-top">
                                                                                <span className="font-medium">
                                                                                    {
                                                                                        action.number
                                                                                    }
                                                                                </span>{' '}
                                                                                {
                                                                                    action.name
                                                                                }
                                                                            </td>
                                                                            <td className="px-3 py-2 text-right align-top">
                                                                                {money(
                                                                                    action.approved_total_cents,
                                                                                )}
                                                                            </td>
                                                                            <td className="px-3 py-2 text-right align-top">
                                                                                <Input
                                                                                    className="ml-auto w-36"
                                                                                    min={
                                                                                        0
                                                                                    }
                                                                                    step="0.01"
                                                                                    type="number"
                                                                                    value={pesosFromCents(
                                                                                        allocation?.amount_cents ??
                                                                                            0,
                                                                                    )}
                                                                                    onChange={(
                                                                                        event,
                                                                                    ) =>
                                                                                        updateAmount(
                                                                                            action.id,
                                                                                            event
                                                                                                .target
                                                                                                .value,
                                                                                        )
                                                                                    }
                                                                                />
                                                                            </td>
                                                                        </tr>
                                                                    );
                                                                },
                                                            )}
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </article>
                                        );
                                    })}
                                </div>
                            </section>
                        ))}
                    </div>
                    <InputError message={form.errors.allocations} />
                </form>
            </main>
        </>
    );
}
