import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, CircleAlert, Scale, Sparkles } from 'lucide-react';
import { useMemo, useState } from 'react';
import { visibleCutCandidates } from '@/components/finance/own-revenue/planning/cuts-state.js';
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
import planning from '@/routes/finance/own-revenue/budgets/planning';
import proposals from '@/routes/finance/own-revenue/budgets/proposals';
import proposalCuts from '@/routes/finance/own-revenue/budgets/proposals/cuts';

type CutCandidate = {
    target_type: string;
    target_id: number;
    stable_key: string;
    format: string;
    activity_id: number;
    activity_code: string;
    activity_name: string;
    specific_item_code: string;
    month: number;
    available_amount_cents: string;
    distributed_amount_cents: string;
};

type Summary = {
    calculated_amount_cents: string;
    abpre_amount_cents: string;
    required_cut_cents: string;
    required_reduction_cents: string;
    required_increase_cents: string;
    distributed_cut_cents: string;
    distributed_reduction_cents: string;
    pending_cut_cents: string;
    pending_reduction_cents: string;
    adjusted_amount_cents: string;
};

type ReconciliationGroup = {
    key: string;
    activity_code: string;
    activity_name: string;
    specific_item_code: string;
    month: number;
    required_increase_cents: string;
};

type Props = {
    budget: { id: number; fiscal_year: number };
    proposal: { id: number; version_number: number };
    summary: Summary;
    groups: ReconciliationGroup[];
    candidates: CutCandidate[];
    suggestion: Record<string, string>;
    blockers: string[];
    reconciliation_fingerprint: string;
    permissions: { manage: boolean };
};

const monthNames = [
    '',
    'Enero',
    'Febrero',
    'Marzo',
    'Abril',
    'Mayo',
    'Junio',
    'Julio',
    'Agosto',
    'Septiembre',
    'Octubre',
    'Noviembre',
    'Diciembre',
];

function money(cents: string): string {
    const value = cents.padStart(3, '0');
    const whole = value.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    return `$${whole}.${value.slice(-2)}`;
}

export default function OwnRevenuePlanningCuts({
    budget,
    proposal,
    summary,
    groups,
    candidates,
    suggestion,
    blockers,
    reconciliation_fingerprint: fingerprint,
    permissions,
}: Props) {
    const [filters, setFilters] = useState({
        format: '',
        activity: '',
        item: '',
        month: '',
    });
    const [amounts, setAmounts] = useState<Record<string, string>>(
        Object.fromEntries(
            candidates.map((candidate) => [
                candidate.stable_key,
                candidate.distributed_amount_cents,
            ]),
        ),
    );
    const visibleKeys = useMemo(
        () =>
            new Set(
                visibleCutCandidates(candidates, filters).map(
                    (candidate) => candidate.stable_key,
                ),
            ),
        [candidates, filters],
    );
    const options = (key: keyof typeof filters) =>
        Array.from(
            new Set(
                candidates.map((candidate) =>
                    key === 'format'
                        ? candidate.format
                        : key === 'activity'
                          ? candidate.activity_code
                          : key === 'item'
                            ? candidate.specific_item_code
                            : String(candidate.month),
                ),
            ),
        ).sort();
    const amountFor = (candidate: CutCandidate) =>
        amounts[candidate.stable_key] ?? '0';
    const submitsCut = (candidate: CutCandidate) => {
        const amount = amountFor(candidate).trim();

        return amount !== '' && amount !== '0';
    };
    const cards: Array<[string, string]> = [
        ['Propuesta calculada', summary.calculated_amount_cents],
        ['Disminuciones requeridas', summary.required_reduction_cents],
        ['Aumentos por conciliación', summary.required_increase_cents],
        ['Disminuciones distribuidas', summary.distributed_reduction_cents],
        ['Saldo por distribuir', summary.pending_reduction_cents],
        ['Propuesta conciliada', summary.adjusted_amount_cents],
        ['ABPRE final', summary.abpre_amount_cents],
    ];
    const increases = groups.filter(
        (group) => group.required_increase_cents !== '0',
    );

    return (
        <>
            <Head
                title={`Conciliación de la propuesta ${proposal.version_number}`}
            />
            <main className="flex h-full flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="grid gap-3">
                    <Button asChild variant="ghost" size="sm" className="w-fit">
                        <Link
                            href={planning.show(budget.id, {
                                query: {
                                    proposal_version: proposal.version_number,
                                },
                            })}
                        >
                            <ArrowLeft className="size-4" />
                            Volver a Planeación
                        </Link>
                    </Button>
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Propuesta calculada · Versión{' '}
                            {proposal.version_number}
                        </p>
                        <h1 className="text-2xl font-semibold">
                            Conciliación con ABPRE {budget.fiscal_year}
                        </h1>
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                            Compara la propuesta con el ABPRE final. Las
                            disminuciones se distribuyen entre necesidades y los
                            importes a favor se agregan como ajustes
                            independientes al crear la propuesta conciliada.
                        </p>
                    </div>
                </header>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {cards.map(([label, amount]) => (
                        <Card key={label}>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm text-muted-foreground">
                                    {label}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="text-xl font-semibold">
                                {money(amount)}
                            </CardContent>
                        </Card>
                    ))}
                </section>

                {blockers.length > 0 && (
                    <Card className="border-amber-300">
                        <CardHeader>
                            <CardTitle>Antes de continuar</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="grid gap-2">
                                {blockers.map((blocker) => (
                                    <li
                                        key={blocker}
                                        className="flex gap-2 text-sm"
                                    >
                                        <CircleAlert className="mt-0.5 size-4 shrink-0 text-amber-600" />
                                        {blocker}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                {increases.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Ajustes automáticos a favor</CardTitle>
                            <CardDescription>
                                Estos importes se agregarán como líneas
                                independientes, sin modificar cantidades, rutas
                                ni tarifas de las necesidades originales.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-2">
                                {increases.map((group) => (
                                    <div
                                        key={group.key}
                                        className="flex flex-col gap-1 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {group.activity_code} · Partida{' '}
                                                {group.specific_item_code}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {group.activity_name} ·{' '}
                                                {monthNames[group.month]}
                                            </p>
                                        </div>
                                        <p className="font-semibold">
                                            {money(
                                                group.required_increase_cents,
                                            )}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {summary.required_reduction_cents !== '0' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Necesidades disponibles para disminuir
                            </CardTitle>
                            <CardDescription>
                                Filtra la lista y captura cuánto disminuir en
                                cada necesidad. La sugerencia sólo se guarda
                                cuando la confirmas.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                {(
                                    [
                                        ['format', 'Formato'],
                                        ['activity', 'Actividad'],
                                        ['item', 'Partida'],
                                        ['month', 'Mes'],
                                    ] as const
                                ).map(([key, label]) => (
                                    <div key={key} className="grid gap-1.5">
                                        <Label htmlFor={`filter-${key}`}>
                                            {label}
                                        </Label>
                                        <select
                                            id={`filter-${key}`}
                                            value={filters[key]}
                                            onChange={(event) =>
                                                setFilters((current) => ({
                                                    ...current,
                                                    [key]: event.target.value,
                                                }))
                                            }
                                            className="h-9 rounded-md border bg-background px-3 text-sm"
                                        >
                                            <option value="">Todos</option>
                                            {options(key).map((value) => (
                                                <option
                                                    key={value}
                                                    value={value}
                                                >
                                                    {key === 'month'
                                                        ? monthNames[
                                                              Number(value)
                                                          ]
                                                        : value}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                ))}
                            </div>

                            {permissions.manage && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="w-fit"
                                    onClick={() => setAmounts(suggestion)}
                                >
                                    <Sparkles className="size-4" />
                                    Usar distribución proporcional
                                </Button>
                            )}

                            <Form
                                action={
                                    proposalCuts.store([budget.id, proposal.id])
                                        .url
                                }
                                method="post"
                            >
                                {({ processing, errors }) => (
                                    <div className="grid gap-4">
                                        <input
                                            type="hidden"
                                            name="reconciliation_fingerprint"
                                            value={fingerprint}
                                        />
                                        <div className="grid gap-2">
                                            {candidates.map(
                                                (candidate, index) => (
                                                    <div
                                                        key={`${candidate.target_type}-${candidate.target_id}`}
                                                        className={`${visibleKeys.has(candidate.stable_key) ? 'grid' : 'hidden'} gap-3 rounded-lg border p-3 md:grid-cols-[1fr_13rem] md:items-end`}
                                                    >
                                                        <div>
                                                            <p className="font-medium">
                                                                {
                                                                    candidate.format
                                                                }{' '}
                                                                ·{' '}
                                                                {
                                                                    candidate.activity_code
                                                                }{' '}
                                                                · Partida{' '}
                                                                {
                                                                    candidate.specific_item_code
                                                                }
                                                            </p>
                                                            <p className="text-sm text-muted-foreground">
                                                                {
                                                                    monthNames[
                                                                        candidate
                                                                            .month
                                                                    ]
                                                                }{' '}
                                                                · Disponible{' '}
                                                                {money(
                                                                    candidate.available_amount_cents,
                                                                )}
                                                            </p>
                                                        </div>
                                                        <div className="grid gap-1.5">
                                                            <Label
                                                                htmlFor={`cut-${index}`}
                                                            >
                                                                Importe a
                                                                disminuir
                                                            </Label>
                                                            <Input
                                                                id={`cut-${index}`}
                                                                name={
                                                                    submitsCut(
                                                                        candidate,
                                                                    )
                                                                        ? `cuts[${index}][amount_cents]`
                                                                        : undefined
                                                                }
                                                                inputMode="numeric"
                                                                value={amountFor(
                                                                    candidate,
                                                                )}
                                                                readOnly={
                                                                    !permissions.manage
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    setAmounts(
                                                                        (
                                                                            current,
                                                                        ) => ({
                                                                            ...current,
                                                                            [candidate.stable_key]:
                                                                                event
                                                                                    .target
                                                                                    .value,
                                                                        }),
                                                                    )
                                                                }
                                                            />
                                                        </div>
                                                        {submitsCut(
                                                            candidate,
                                                        ) && (
                                                            <>
                                                                <input
                                                                    type="hidden"
                                                                    name={`cuts[${index}][target_type]`}
                                                                    value={
                                                                        candidate.target_type
                                                                    }
                                                                />
                                                                <input
                                                                    type="hidden"
                                                                    name={`cuts[${index}][target_id]`}
                                                                    value={
                                                                        candidate.target_id
                                                                    }
                                                                />
                                                                <input
                                                                    type="hidden"
                                                                    name={`cuts[${index}][stable_key]`}
                                                                    value={
                                                                        candidate.stable_key
                                                                    }
                                                                />
                                                                <input
                                                                    type="hidden"
                                                                    name={`cuts[${index}][specific_item_code]`}
                                                                    value={
                                                                        candidate.specific_item_code
                                                                    }
                                                                />
                                                            </>
                                                        )}
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                        {(errors.cuts ||
                                            errors.reconciliation_fingerprint) && (
                                            <p className="text-sm text-destructive">
                                                {errors.cuts ??
                                                    errors.reconciliation_fingerprint}
                                            </p>
                                        )}
                                        {permissions.manage && (
                                            <Button
                                                type="submit"
                                                disabled={
                                                    processing ||
                                                    blockers.length > 0
                                                }
                                                className="w-fit"
                                            >
                                                <Scale className="size-4" />
                                                {processing
                                                    ? 'Guardando…'
                                                    : 'Guardar distribución'}
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}

                {permissions.manage &&
                    summary.pending_reduction_cents === '0' &&
                    blockers.length === 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    Crear propuesta conciliada
                                </CardTitle>
                                <CardDescription>
                                    Se conservará la versión calculada y se
                                    creará una nueva versión conciliada.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    action={
                                        proposals.adjust([
                                            budget.id,
                                            proposal.id,
                                        ]).url
                                    }
                                    method="post"
                                >
                                    {({ processing, errors }) => (
                                        <div className="grid gap-2">
                                            <input
                                                type="hidden"
                                                name="reconciliation_fingerprint"
                                                value={fingerprint}
                                            />
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                                className="w-fit"
                                            >
                                                {processing
                                                    ? 'Creando…'
                                                    : 'Crear propuesta conciliada'}
                                            </Button>
                                            {(errors.cuts ||
                                                errors.reconciliation_fingerprint) && (
                                                <p className="text-sm text-destructive">
                                                    {errors.cuts ??
                                                        errors.reconciliation_fingerprint}
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    )}
            </main>
        </>
    );
}
