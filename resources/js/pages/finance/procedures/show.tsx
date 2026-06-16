import { Head, Link, useForm } from '@inertiajs/react';
import {
    Banknote,
    CreditCard,
    Eye,
    Landmark,
    ReceiptText,
    UserRound,
} from 'lucide-react';
import type { FormEvent } from 'react';
import {
    money,
    ProcedureStatusBadge,
    ReceiptStatusBadge,
    ReceiptTypeBadge,
    SeqDepositStatusBadge,
} from '@/components/finance/finance-badges';
import { Button } from '@/components/ui/button';

type Procedure = {
    id: number;
    folio: string | null;
    status: string;
    total_pesos: number;
    can_register_payment: boolean;
    student: {
        name: string;
        grade: string | null;
        group: string | null;
    };
    items: Array<{
        id: number;
        concept_name: string;
        concept_type: string;
        quantity: number;
        unit_amount_pesos: number;
        subtotal_pesos: number;
    }>;
    receipts: Array<{
        id: number;
        folio: string;
        type: string;
        status: string;
        total_pesos: number;
        can_register_seq_deposit: boolean;
        seq_deposit: {
            id: number;
            deposit_date: string | null;
            bank_transaction_folio: string;
            deposit_type: string;
            deposit_concept: string;
            amount_pesos: number;
        } | null;
    }>;
};

type Props = {
    procedure: Procedure;
};

export default function PaymentProcedureShow({ procedure }: Props) {
    const externalReceipts = procedure.receipts.filter(
        (receipt) => receipt.type === 'external',
    );
    const paymentForm = useForm({
        payment_method: 'cash',
        reference: '',
    });

    function registerPayment(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        paymentForm.post(
            `/finance/payment-procedures/${procedure.id}/payments`,
            {
                preserveScroll: true,
            },
        );
    }

    return (
        <>
            <Head title={`Trámite ${procedure.folio ?? `#${procedure.id}`}`} />
            <main className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <header className="rounded-lg border bg-muted/20 p-4">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div className="grid gap-2">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-xl font-semibold">
                                    Trámite {procedure.folio ?? `#${procedure.id}`}
                                </h1>
                                <ProcedureStatusBadge
                                    status={procedure.status}
                                />
                            </div>
                            <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                <UserRound className="size-4" />
                                <span>{procedure.student.name}</span>
                                <span>
                                    {[procedure.student.grade, procedure.student.group]
                                        .filter(Boolean)
                                        .join(' ')}
                                </span>
                            </div>
                        </div>
                        <div className="grid gap-1 text-left lg:text-right">
                            <span className="text-sm text-muted-foreground">
                                Total del trámite
                            </span>
                            <span className="text-2xl font-semibold tabular-nums">
                                {money(procedure.total_pesos)}
                            </span>
                        </div>
                    </div>
                </header>
                {procedure.can_register_payment ? (
                    <section className="rounded-lg border border-sky-200 bg-sky-50/60 p-3 dark:border-sky-900 dark:bg-sky-950/20">
                        <div className="mb-3 flex items-center gap-2">
                            <span className="grid size-8 place-items-center rounded-md bg-sky-600 text-white">
                                <CreditCard className="size-4" />
                            </span>
                            <div>
                                <h2 className="text-sm font-semibold">
                                    Registrar pago
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Confirma el cobro recibido en caja.
                                </p>
                            </div>
                        </div>
                        <form
                            className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]"
                            onSubmit={registerPayment}
                        >
                            <label className="grid gap-1 text-sm">
                                <span className="text-muted-foreground">
                                    Forma de pago
                                </span>
                                <select
                                    className="h-9 rounded-md border bg-background px-2"
                                    value={paymentForm.data.payment_method}
                                    onChange={(event) =>
                                        paymentForm.setData(
                                            'payment_method',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="cash">Efectivo</option>
                                    <option value="transfer">Transferencia</option>
                                    <option value="deposit">Depósito</option>
                                </select>
                                {paymentForm.errors.payment_method ? (
                                    <span className="text-xs text-destructive">
                                        {paymentForm.errors.payment_method}
                                    </span>
                                ) : null}
                            </label>
                            <label className="grid gap-1 text-sm">
                                <span className="text-muted-foreground">
                                    Referencia
                                </span>
                                <input
                                    className="h-9 rounded-md border bg-background px-2"
                                    value={paymentForm.data.reference}
                                    onChange={(event) =>
                                        paymentForm.setData(
                                            'reference',
                                            event.target.value,
                                        )
                                    }
                                />
                                {paymentForm.errors.reference ? (
                                    <span className="text-xs text-destructive">
                                        {paymentForm.errors.reference}
                                    </span>
                                ) : null}
                            </label>
                            <Button
                                className="self-end"
                                disabled={paymentForm.processing}
                                type="submit"
                            >
                                <Banknote className="size-4" />
                                Registrar pago
                            </Button>
                        </form>
                    </section>
                ) : null}
                <section className="overflow-hidden rounded-lg border">
                    <div className="flex items-center gap-2 border-b bg-muted/60 px-3 py-2">
                        <ReceiptText className="size-4 text-muted-foreground" />
                        <h2 className="text-sm font-semibold">
                            Conceptos del trámite
                        </h2>
                    </div>
                    <table className="w-full text-sm">
                        <thead className="bg-muted text-left">
                            <tr>
                                <th className="px-3 py-2">Concepto</th>
                                <th className="px-3 py-2">Tipo</th>
                                <th className="px-3 py-2 text-right">Cantidad</th>
                                <th className="px-3 py-2 text-right">Importe</th>
                                <th className="px-3 py-2 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            {procedure.items.map((item) => (
                                <tr key={item.id} className="border-t">
                                    <td className="px-3 py-2">{item.concept_name}</td>
                                    <td className="px-3 py-2">
                                        <ReceiptTypeBadge
                                            type={item.concept_type}
                                        />
                                    </td>
                                    <td className="px-3 py-2 text-right">{item.quantity}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">
                                        {money(item.unit_amount_pesos)}
                                    </td>
                                    <td className="px-3 py-2 text-right font-medium tabular-nums">
                                        {money(item.subtotal_pesos)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
                {procedure.receipts.length > 0 ? (
                    <section className="overflow-x-auto rounded-lg border">
                        <div className="flex items-center gap-2 border-b bg-muted/60 px-3 py-2">
                            <ReceiptText className="size-4 text-muted-foreground" />
                            <h2 className="text-sm font-semibold">Recibos emitidos</h2>
                        </div>
                        <table className="min-w-[42rem] w-full text-sm">
                            <thead className="bg-muted/60 text-left">
                                <tr>
                                    <th className="px-3 py-2">Folio</th>
                                    <th className="px-3 py-2">Tipo</th>
                                    <th className="px-3 py-2">Estado</th>
                                    <th className="px-3 py-2 text-right">Total</th>
                                    <th className="px-3 py-2 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                {procedure.receipts.map((receipt) => (
                                    <tr key={receipt.id} className="border-t">
                                        <td className="px-3 py-2">
                                            <Link
                                                className="font-medium hover:underline"
                                                href={`/finance/receipts/${receipt.id}`}
                                            >
                                                {receipt.folio}
                                            </Link>
                                        </td>
                                        <td className="px-3 py-2">
                                            <ReceiptTypeBadge
                                                type={receipt.type}
                                            />
                                        </td>
                                        <td className="px-3 py-2">
                                            <ReceiptStatusBadge
                                                status={receipt.status}
                                            />
                                        </td>
                                        <td className="px-3 py-2 text-right font-medium tabular-nums">
                                            {money(receipt.total_pesos)}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link
                                                    href={`/finance/receipts/${receipt.id}`}
                                                >
                                                    <Eye className="size-4" />
                                                    Ver
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>
                ) : null}
                {externalReceipts.length > 0 ? (
                    <section className="overflow-hidden rounded-lg border">
                        <div className="flex items-center gap-2 border-b bg-muted/60 px-3 py-2">
                            <Landmark className="size-4 text-muted-foreground" />
                            <h2 className="text-sm font-semibold">
                                Depósitos SEQ
                            </h2>
                        </div>
                        <div className="divide-y">
                            {externalReceipts.map((receipt) => (
                                <SeqDepositPanel
                                    key={receipt.id}
                                    receipt={receipt}
                                />
                            ))}
                        </div>
                    </section>
                ) : null}
            </main>
        </>
    );
}

function SeqDepositPanel({
    receipt,
}: {
    receipt: Procedure['receipts'][number];
}) {
    const form = useForm({
        deposit_date: '',
        bank_transaction_folio: '',
        deposit_type: 'ventanilla',
        deposit_concept: '',
        amount_pesos: receipt.total_pesos,
        notes: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.post(`/finance/receipts/${receipt.id}/seq-deposit`, {
            preserveScroll: true,
        });
    }

    return (
        <div className="grid gap-3 p-3">
            <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <Link
                        className="text-sm font-medium"
                        href={`/finance/receipts/${receipt.id}`}
                    >
                        {receipt.folio}
                    </Link>
                    <p className="text-sm text-muted-foreground">
                        {money(receipt.total_pesos)}
                    </p>
                </div>
                <SeqDepositStatusBadge registered={Boolean(receipt.seq_deposit)} />
            </div>

            {receipt.seq_deposit ? (
                <dl className="grid gap-2 text-sm sm:grid-cols-4">
                    <div>
                        <dt className="text-muted-foreground">Fecha</dt>
                        <dd>{receipt.seq_deposit.deposit_date}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Folio banco</dt>
                        <dd>{receipt.seq_deposit.bank_transaction_folio}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Tipo</dt>
                        <dd>{receipt.seq_deposit.deposit_type}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Concepto</dt>
                        <dd>{receipt.seq_deposit.deposit_concept}</dd>
                    </div>
                </dl>
            ) : receipt.can_register_seq_deposit ? (
                <form
                    className="grid gap-3 lg:grid-cols-[repeat(5,minmax(0,1fr))_auto]"
                    onSubmit={submit}
                >
                    <label className="grid gap-1 text-sm">
                        <span className="text-muted-foreground">Fecha</span>
                        <input
                            className="h-9 rounded-md border bg-background px-2"
                            type="date"
                            value={form.data.deposit_date}
                            onChange={(event) =>
                                form.setData(
                                    'deposit_date',
                                    event.target.value,
                                )
                            }
                        />
                        {form.errors.deposit_date ? (
                            <span className="text-xs text-destructive">
                                {form.errors.deposit_date}
                            </span>
                        ) : null}
                    </label>
                    <label className="grid gap-1 text-sm">
                        <span className="text-muted-foreground">
                            Folio banco
                        </span>
                        <input
                            className="h-9 rounded-md border bg-background px-2"
                            value={form.data.bank_transaction_folio}
                            onChange={(event) =>
                                form.setData(
                                    'bank_transaction_folio',
                                    event.target.value,
                                )
                            }
                        />
                        {form.errors.bank_transaction_folio ? (
                            <span className="text-xs text-destructive">
                                {form.errors.bank_transaction_folio}
                            </span>
                        ) : null}
                    </label>
                    <label className="grid gap-1 text-sm">
                        <span className="text-muted-foreground">Tipo</span>
                        <select
                            className="h-9 rounded-md border bg-background px-2"
                            value={form.data.deposit_type}
                            onChange={(event) =>
                                form.setData(
                                    'deposit_type',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="ventanilla">Ventanilla</option>
                            <option value="practicaja">Practicaja</option>
                            <option value="transferencia">Transferencia</option>
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">
                        <span className="text-muted-foreground">Concepto</span>
                        <input
                            className="h-9 rounded-md border bg-background px-2"
                            value={form.data.deposit_concept}
                            onChange={(event) =>
                                form.setData(
                                    'deposit_concept',
                                    event.target.value,
                                )
                            }
                        />
                        {form.errors.deposit_concept ? (
                            <span className="text-xs text-destructive">
                                {form.errors.deposit_concept}
                            </span>
                        ) : null}
                    </label>
                    <label className="grid gap-1 text-sm">
                        <span className="text-muted-foreground">Importe</span>
                        <input
                            className="h-9 rounded-md border bg-background px-2"
                            inputMode="numeric"
                            value={form.data.amount_pesos}
                            onChange={(event) =>
                                form.setData(
                                    'amount_pesos',
                                    Number(event.target.value),
                                )
                            }
                        />
                        {form.errors.amount_pesos ? (
                            <span className="text-xs text-destructive">
                                {form.errors.amount_pesos}
                            </span>
                        ) : null}
                    </label>
                    <Button
                        className="self-end"
                        disabled={form.processing}
                        type="submit"
                    >
                        Registrar
                    </Button>
                </form>
            ) : null}
        </div>
    );
}
