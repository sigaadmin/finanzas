import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Fuel, WalletCards } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import budgets from '@/routes/finance/own-revenue/budgets';
import fuel from '@/routes/finance/own-revenue/budgets/fuel';

type Props = {
    budget: { id: number; fiscal_year: number; status: string; fuel_budget_month: number };
    fund: null | {
        id: number;
        acquired_amount_cents: string;
        source_dossier: { id: number; folio: string; paid_amount_cents: string };
        opened_by_name: string;
        opened_at: string | null;
    };
    summary: {
        acquired_amount_cents: string;
        confirmed_consumption_cents: string;
        pending_needs_cents: string;
        available_amount_cents: string;
    };
    eligible_dossiers: Array<{
        id: number;
        folio: string;
        paid_amount_cents: string;
        paid_at: string | null;
    }>;
    permissions: { open_fund: boolean };
};

function formatCents(value: string): string {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(BigInt(value)) / 100);
}

function pesosToCents(value: string): string | null {
    const match = value.trim().match(/^(\d+)(?:\.(\d{1,2}))?$/);

    if (match === null) {
        return null;
    }

    return (BigInt(match[1]) * 100n + BigInt((match[2] ?? '').padEnd(2, '0'))).toString();
}

export default function OwnRevenueFuelShow({ budget, fund: fundData, summary, eligible_dossiers: dossiers, permissions }: Props) {
    const form = useForm({
        source_expense_dossier_id: dossiers[0]?.id.toString() ?? '',
        acquired_amount_pesos: dossiers[0] === undefined ? '' : (Number(BigInt(dossiers[0].paid_amount_cents)) / 100).toFixed(2),
        acquired_amount_cents: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        const cents = pesosToCents(form.data.acquired_amount_pesos);

        if (cents === null || cents === '0') {
            form.setError('acquired_amount_cents', 'Captura el valor realmente adquirido.');

            return;
        }

        form.transform((data) => ({ ...data, acquired_amount_cents: cents }));
        form.post(fuel.store(budget.id).url, { preserveScroll: true });
    };

    return (
        <>
            <Head title={`Combustible ${budget.fiscal_year}`} />
            <main className="flex h-full flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="grid gap-3">
                    <Button asChild variant="ghost" size="sm" className="w-fit">
                        <Link href={budgets.show(budget.id)}>
                            <ArrowLeft className="size-4" /> Volver al presupuesto
                        </Link>
                    </Button>
                    <div>
                        <p className="text-sm text-muted-foreground">Control operativo independiente</p>
                        <h1 className="mt-1 flex items-center gap-2 text-2xl font-semibold">
                            <Fuel className="size-6" /> Combustible {budget.fiscal_year}
                        </h1>
                    </div>
                </header>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Saldos del fondo">
                    <SummaryCard label="Fondo adquirido" value={summary.acquired_amount_cents} />
                    <SummaryCard label="Consumo confirmado" value={summary.confirmed_consumption_cents} />
                    <SummaryCard label="Necesidades pendientes" value={summary.pending_needs_cents} />
                    <SummaryCard label="Saldo disponible" value={summary.available_amount_cents} accent />
                </section>

                {fundData === null ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Abrir fondo operativo</CardTitle>
                            <CardDescription>
                                Usa el valor realmente adquirido con el expediente pagado de combustible de abril.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {permissions.open_fund && dossiers.length > 0 ? (
                                <form onSubmit={submit} className="grid max-w-xl gap-4">
                                    <div className="grid gap-2">
                                        <Label>Expediente pagado</Label>
                                        <select
                                            value={form.data.source_expense_dossier_id}
                                            onChange={(event) => form.setData('source_expense_dossier_id', event.target.value)}
                                            className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                            required
                                        >
                                            {dossiers.map((dossier) => (
                                                <option key={dossier.id} value={dossier.id}>
                                                    {dossier.folio} · {formatCents(dossier.paid_amount_cents)}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={form.errors.source_expense_dossier_id} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Valor realmente adquirido (pesos)</Label>
                                        <Input
                                            value={form.data.acquired_amount_pesos}
                                            onChange={(event) => form.setData('acquired_amount_pesos', event.target.value)}
                                            inputMode="decimal"
                                            placeholder="0.00"
                                            required
                                        />
                                        <InputError message={form.errors.acquired_amount_cents} />
                                    </div>
                                    <Button type="submit" disabled={form.processing}>
                                        <WalletCards className="size-4" />
                                        {form.processing ? 'Abriendo…' : 'Abrir fondo operativo'}
                                    </Button>
                                </form>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Primero registra como pagado el expediente de la partida 26101 correspondiente a abril.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <Card>
                        <CardHeader className="gap-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <CardTitle>Fondo operativo abierto</CardTitle>
                                <Badge variant="outline">{fundData.source_dossier.folio}</Badge>
                            </div>
                            <CardDescription>
                                Registrado por {fundData.opened_by_name}. El fondo ya es independiente del gasto presupuestal.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                )}
            </main>
        </>
    );
}

function SummaryCard({ label, value, accent = false }: { label: string; value: string; accent?: boolean }) {
    return (
        <Card className={accent ? 'border-emerald-300 dark:border-emerald-800' : undefined}>
            <CardHeader className="pb-2"><CardTitle className="text-sm text-muted-foreground">{label}</CardTitle></CardHeader>
            <CardContent><p className="text-xl font-semibold">{formatCents(value)}</p></CardContent>
        </Card>
    );
}
