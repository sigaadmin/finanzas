import { Head, Link, useForm } from '@inertiajs/react';
import { LoaderCircle, Save } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import finance from '@/routes/finance';

type RequestedItem = {
    id: number;
    expense_concept: string;
    expense_item: string;
    period: number;
    total_cents: number;
    approved_amount_cents: number | null;
    approved_percentage: string | null;
};

type U300Action = {
    id: number;
    number: string;
    name: string;
    requested_total_cents: number;
    approved_total_cents: number | null;
    items: RequestedItem[];
};

type Goal = {
    id: number;
    number: string;
    description: string;
    requested_total_cents: number;
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
        requested_total_cents: number;
        approved_total_cents: number | null;
        federal_authorized_total_cents: number | null;
        projects: Project[];
    };
};

type VerdictFormData = {
    federal_authorized_total_cents: number | null;
    items: Array<{
        id: number;
        approved_amount_cents: number | null;
        approved_percentage: number | null;
    }>;
};

function money(cents: number | null): string {
    return ((cents ?? 0) / 100).toLocaleString('es-MX', {
        style: 'currency',
        currency: 'MXN',
    });
}

function centsFromPesos(value: string): number | null {
    if (value.trim() === '') {
        return null;
    }

    return Math.round(Number(value) * 100);
}

function pesosFromCents(value: number | null): string {
    if (value === null) {
        return '';
    }

    return String(value / 100);
}

export default function U300Verdict({ program }: Props) {
    const initialItems = program.projects.flatMap((project) =>
        project.goals.flatMap((goal) =>
            goal.actions.flatMap((action) =>
                action.items.map((item) => ({
                    id: item.id,
                    approved_amount_cents: item.approved_amount_cents,
                    approved_percentage: item.approved_percentage
                        ? Number(item.approved_percentage)
                        : null,
                })),
            ),
        ),
    );

    const form = useForm<VerdictFormData>({
        federal_authorized_total_cents: program.federal_authorized_total_cents,
        items: initialItems,
    });

    const approvedTotal = form.data.items.reduce(
        (total, item) => total + (item.approved_amount_cents ?? 0),
        0,
    );

    function formItem(
        itemId: number,
    ): VerdictFormData['items'][number] | undefined {
        return form.data.items.find((item) => item.id === itemId);
    }

    function actionApprovedTotal(action: U300Action): number {
        return action.items.reduce(
            (total, item) =>
                total + (formItem(item.id)?.approved_amount_cents ?? 0),
            0,
        );
    }

    function actionPercentageValue(action: U300Action): string {
        const firstItem = action.items[0];

        if (!firstItem) {
            return '';
        }

        const firstPercentage = formItem(firstItem.id)?.approved_percentage;

        if (firstPercentage === null || firstPercentage === undefined) {
            return '';
        }

        return String(firstPercentage);
    }

    function updateActionPercentage(action: U300Action, value: string): void {
        const percentage = value.trim() === '' ? null : Number(value);
        const actionItems = new Map(
            action.items.map((item) => [item.id, item]),
        );

        form.setData(
            'items',
            form.data.items.map((item) => {
                const requestedItem = actionItems.get(item.id);

                if (!requestedItem) {
                    return item;
                }

                return {
                    ...item,
                    approved_amount_cents:
                        percentage === null
                            ? null
                            : Math.round(
                                  requestedItem.total_cents *
                                      (percentage / 100),
                              ),
                    approved_percentage: percentage,
                };
            }),
        );
    }

    function updateFederalAuthorizedTotal(value: string): void {
        form.setData('federal_authorized_total_cents', centsFromPesos(value));
    }

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.put(finance.u300.programs.verdict.update(program).url);
    }

    return (
        <>
            <Head title="Veredicto federal U300" />
            <main className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <header className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Presupuesto U300 · {program.fiscal_year}
                        </p>
                        <h1 className="text-xl font-semibold">
                            Veredicto federal
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
                            form="u300-verdict-form"
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
                            Solicitado
                        </p>
                        <p className="mt-2 text-xl font-semibold">
                            {money(program.requested_total_cents)}
                        </p>
                    </div>
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">
                            Dictaminado
                        </p>
                        <p className="mt-2 text-xl font-semibold">
                            {money(approvedTotal)}
                        </p>
                    </div>
                    <div className="rounded-lg border p-4">
                        <label className="text-sm text-muted-foreground">
                            Autorizado federal
                        </label>
                        <Input
                            className="mt-2"
                            min={0}
                            step="0.01"
                            type="number"
                            value={pesosFromCents(
                                form.data.federal_authorized_total_cents,
                            )}
                            onChange={(event) =>
                                updateFederalAuthorizedTotal(event.target.value)
                            }
                        />
                    </div>
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">
                            No autorizado
                        </p>
                        <p className="mt-2 text-xl font-semibold">
                            {money(
                                program.requested_total_cents -
                                    (form.data.federal_authorized_total_cents ??
                                        approvedTotal),
                            )}
                        </p>
                    </div>
                </section>

                <form id="u300-verdict-form" onSubmit={submit}>
                    <InputError
                        message={form.errors.federal_authorized_total_cents}
                    />
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
                                        const goalItemIds =
                                            goal.actions.flatMap((action) =>
                                                action.items.map(
                                                    (item) => item.id,
                                                ),
                                            );
                                        const goalApproved = form.data.items
                                            .filter((item) =>
                                                goalItemIds.includes(item.id),
                                            )
                                            .reduce(
                                                (total, item) =>
                                                    total +
                                                    (item.approved_amount_cents ??
                                                        0),
                                                0,
                                            );

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
                                                        <p className="text-sm text-muted-foreground">
                                                            Bolsa autorizada de
                                                            meta:{' '}
                                                            {money(
                                                                goalApproved,
                                                            )}
                                                        </p>
                                                    </div>
                                                    <p className="text-sm text-muted-foreground">
                                                        Solicitado:{' '}
                                                        {money(
                                                            goal.requested_total_cents,
                                                        )}
                                                    </p>
                                                </div>

                                                <div className="grid gap-3">
                                                    {goal.actions.map(
                                                        (action) => (
                                                            <section
                                                                key={action.id}
                                                                className="overflow-hidden rounded-lg border"
                                                            >
                                                                <div className="grid gap-3 border-b bg-muted/30 px-3 py-3 lg:grid-cols-[minmax(0,1fr)_auto_auto_auto] lg:items-start">
                                                                    <div className="min-w-0">
                                                                        <p className="text-xs font-medium text-muted-foreground">
                                                                            Acción{' '}
                                                                            {
                                                                                action.number
                                                                            }
                                                                        </p>
                                                                        <h4 className="mt-1 text-sm leading-6 font-semibold">
                                                                            {
                                                                                action.name
                                                                            }
                                                                        </h4>
                                                                    </div>
                                                                    <div className="text-sm">
                                                                        <p className="text-xs text-muted-foreground">
                                                                            Solicitado
                                                                        </p>
                                                                        <p className="font-semibold">
                                                                            {money(
                                                                                action.requested_total_cents,
                                                                            )}
                                                                        </p>
                                                                    </div>
                                                                    <div className="text-sm">
                                                                        <p className="text-xs text-muted-foreground">
                                                                            Aprobado
                                                                        </p>
                                                                        <p className="font-semibold">
                                                                            {money(
                                                                                actionApprovedTotal(
                                                                                    action,
                                                                                ),
                                                                            )}
                                                                        </p>
                                                                    </div>
                                                                    <div>
                                                                        <label className="text-xs text-muted-foreground">
                                                                            %
                                                                            aprobado
                                                                        </label>
                                                                        <Input
                                                                            className="mt-1 w-28"
                                                                            max={
                                                                                100
                                                                            }
                                                                            min={
                                                                                0
                                                                            }
                                                                            step="0.01"
                                                                            type="number"
                                                                            value={actionPercentageValue(
                                                                                action,
                                                                            )}
                                                                            onChange={(
                                                                                event,
                                                                            ) =>
                                                                                updateActionPercentage(
                                                                                    action,
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                                )
                                                                            }
                                                                        />
                                                                    </div>
                                                                </div>
                                                                <div className="overflow-x-auto">
                                                                    <table className="min-w-[760px] text-sm">
                                                                        <thead className="bg-muted/60 text-left">
                                                                            <tr>
                                                                                <th className="px-3 py-2">
                                                                                    Rubro
                                                                                </th>
                                                                                <th className="px-3 py-2 text-right">
                                                                                    Solicitado
                                                                                </th>
                                                                                <th className="px-3 py-2 text-right">
                                                                                    Aprobado
                                                                                </th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            {action.items.map(
                                                                                (
                                                                                    item,
                                                                                ) => (
                                                                                    <tr
                                                                                        key={
                                                                                            item.id
                                                                                        }
                                                                                        className="border-t"
                                                                                    >
                                                                                        <td className="px-3 py-2">
                                                                                            {
                                                                                                item.expense_concept
                                                                                            }{' '}
                                                                                            ·{' '}
                                                                                            {
                                                                                                item.expense_item
                                                                                            }
                                                                                        </td>
                                                                                        <td className="px-3 py-2 text-right">
                                                                                            {money(
                                                                                                item.total_cents,
                                                                                            )}
                                                                                        </td>
                                                                                        <td className="px-3 py-2 text-right">
                                                                                            {money(
                                                                                                formItem(
                                                                                                    item.id,
                                                                                                )
                                                                                                    ?.approved_amount_cents ??
                                                                                                    null,
                                                                                            )}
                                                                                        </td>
                                                                                    </tr>
                                                                                ),
                                                                            )}
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </section>
                                                        ),
                                                    )}
                                                </div>
                                            </article>
                                        );
                                    })}
                                </div>
                            </section>
                        ))}
                    </div>
                    <InputError message={form.errors.items} />
                </form>
            </main>
        </>
    );
}
