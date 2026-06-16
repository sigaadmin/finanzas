import { Head, Link, useForm } from '@inertiajs/react';
import { Check, LoaderCircle, PencilLine } from 'lucide-react';
import { Button } from '@/components/ui/button';
import finance from '@/routes/finance';

type RequestedItem = {
    expense_concept: string;
    expense_item: string;
    period: number;
    quantity: number;
    unit_price_cents: number;
    total_cents: number;
};

type U300Action = {
    number: string;
    name: string;
    justification: string;
    items: RequestedItem[];
};

type Goal = {
    number: string;
    description: string;
    requested_total_cents: number;
    actions: U300Action[];
};

type Project = {
    number: string;
    name: string;
    justification: string;
    goals: Goal[];
};

type Props = {
    preview: {
        fiscal_year: number;
        source_filename: string;
        parsed: {
            general: {
                name: string;
                requested_total_cents: number;
            };
            responsible: {
                name: string;
                email: string;
            };
            projects: Project[];
        };
    };
};

function money(cents: number): string {
    return (cents / 100).toLocaleString('es-MX', {
        style: 'currency',
        currency: 'MXN',
    });
}

function actionTotal(action: U300Action): number {
    return action.items.reduce((total, item) => total + item.total_cents, 0);
}

export default function U300ImportPreview({ preview }: Props) {
    const form = useForm({});
    const goals = preview.parsed.projects.flatMap((project) => project.goals);
    const actions = goals.flatMap((goal) => goal.actions);
    const items = actions.flatMap((action) => action.items);

    function submit(): void {
        form.post(finance.u300.imports.store().url);
    }

    return (
        <>
            <Head title="Revisar importación U300" />
            <main className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <header className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            {preview.source_filename} · {preview.fiscal_year}
                        </p>
                        <h1 className="text-xl font-semibold">
                            {preview.parsed.general.name}
                        </h1>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href={finance.u300.imports.create()}>
                                <PencilLine className="size-4" />
                                Cambiar PDF
                            </Link>
                        </Button>
                        <Button disabled={form.processing} onClick={submit}>
                            {form.processing ? (
                                <LoaderCircle className="size-4 animate-spin" />
                            ) : (
                                <Check className="size-4" />
                            )}
                            Confirmar
                        </Button>
                    </div>
                </header>

                <section className="grid gap-3 md:grid-cols-4">
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">
                            Solicitado
                        </p>
                        <p className="mt-2 text-xl font-semibold">
                            {money(
                                preview.parsed.general.requested_total_cents,
                            )}
                        </p>
                    </div>
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">
                            Proyectos
                        </p>
                        <p className="mt-2 text-xl font-semibold">
                            {preview.parsed.projects.length}
                        </p>
                    </div>
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">Metas</p>
                        <p className="mt-2 text-xl font-semibold">
                            {goals.length}
                        </p>
                    </div>
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">Rubros</p>
                        <p className="mt-2 text-xl font-semibold">
                            {items.length}
                        </p>
                    </div>
                </section>

                <section className="grid gap-3">
                    {preview.parsed.projects.map((project) => (
                        <article
                            key={project.number}
                            className="rounded-lg border"
                        >
                            <div className="border-b bg-muted/50 px-4 py-3">
                                <h2 className="text-sm font-semibold">
                                    {project.number}. {project.name}
                                </h2>
                            </div>
                            <div className="grid gap-3 p-4">
                                {project.goals.map((goal) => (
                                    <div
                                        key={goal.number}
                                        className="grid gap-3"
                                    >
                                        <h3 className="text-sm leading-6 font-semibold">
                                            {goal.number} {goal.description}
                                        </h3>
                                        <div className="grid gap-3">
                                            {goal.actions.map((action) => (
                                                <section
                                                    key={action.number}
                                                    className="overflow-hidden rounded-lg border"
                                                >
                                                    <div className="flex flex-col gap-2 border-b bg-muted/30 px-3 py-3 md:flex-row md:items-start md:justify-between">
                                                        <div className="min-w-0">
                                                            <p className="text-xs font-medium text-muted-foreground">
                                                                Acción{' '}
                                                                {action.number}
                                                            </p>
                                                            <h4 className="mt-1 text-sm leading-6 font-semibold">
                                                                {action.name}
                                                            </h4>
                                                        </div>
                                                        <div className="shrink-0 text-left md:text-right">
                                                            <p className="text-xs text-muted-foreground">
                                                                Total acción
                                                            </p>
                                                            <p className="text-sm font-semibold">
                                                                {money(
                                                                    actionTotal(
                                                                        action,
                                                                    ),
                                                                )}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    {action.items.length > 0 ? (
                                                        <div className="overflow-x-auto">
                                                            <table className="min-w-[820px] text-sm">
                                                                <thead className="bg-muted/60 text-left">
                                                                    <tr>
                                                                        <th className="px-3 py-2">
                                                                            Concepto
                                                                        </th>
                                                                        <th className="px-3 py-2">
                                                                            Rubro
                                                                        </th>
                                                                        <th className="px-3 py-2 text-center">
                                                                            Periodo
                                                                        </th>
                                                                        <th className="px-3 py-2 text-right">
                                                                            Cantidad
                                                                        </th>
                                                                        <th className="px-3 py-2 text-right">
                                                                            Precio
                                                                            unitario
                                                                        </th>
                                                                        <th className="px-3 py-2 text-right">
                                                                            Total
                                                                        </th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    {action.items.map(
                                                                        (
                                                                            item,
                                                                        ) => (
                                                                            <tr
                                                                                key={`${action.number}-${item.expense_concept}-${item.expense_item}`}
                                                                                className="border-t"
                                                                            >
                                                                                <td className="px-3 py-2">
                                                                                    {
                                                                                        item.expense_concept
                                                                                    }
                                                                                </td>
                                                                                <td className="px-3 py-2">
                                                                                    {
                                                                                        item.expense_item
                                                                                    }
                                                                                </td>
                                                                                <td className="px-3 py-2 text-center">
                                                                                    {
                                                                                        item.period
                                                                                    }
                                                                                </td>
                                                                                <td className="px-3 py-2 text-right">
                                                                                    {item.quantity.toLocaleString(
                                                                                        'es-MX',
                                                                                    )}
                                                                                </td>
                                                                                <td className="px-3 py-2 text-right">
                                                                                    {money(
                                                                                        item.unit_price_cents,
                                                                                    )}
                                                                                </td>
                                                                                <td className="px-3 py-2 text-right">
                                                                                    {money(
                                                                                        item.total_cents,
                                                                                    )}
                                                                                </td>
                                                                            </tr>
                                                                        ),
                                                                    )}
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    ) : (
                                                        <p className="px-3 py-3 text-sm text-muted-foreground">
                                                            Sin rubros
                                                            detectados
                                                        </p>
                                                    )}
                                                </section>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </article>
                    ))}
                </section>
            </main>
        </>
    );
}
