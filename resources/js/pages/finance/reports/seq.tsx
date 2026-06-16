import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';

type Row = {
    id: number;
    folio: string;
    issued_at: string | null;
    student_name: string;
    grade: string | null;
    group: string | null;
    concept_name: string | null;
    status: string;
    total_pesos: number;
    amount_in_words: string;
    seq_deposit: {
        deposit_date: string | null;
        bank_transaction_folio: string;
        deposit_type: string;
        deposit_concept: string;
        amount_pesos: number;
    } | null;
};

type Props = {
    filters: {
        from: string | null;
        to: string | null;
    };
    rows: Row[];
    totals: {
        receipts: number;
        total_pesos: number;
    };
};

export default function SeqReport({ filters, rows, totals }: Props) {
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    const query = new URLSearchParams();

    if (from) {
        query.set('from', from);
    }

    if (to) {
        query.set('to', to);
    }

    const exportHref = `/finance/seq-report/export${query.toString() ? `?${query.toString()}` : ''}`;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        router.get(
            '/finance/seq-report',
            {
                from: from || undefined,
                to: to || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    }

    return (
        <>
            <Head title="Reporte SEQ" />
            <main className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-sm text-muted-foreground">Finanzas externas</p>
                        <h1 className="text-xl font-semibold">Reporte SEQ</h1>
                    </div>
                    <form className="flex flex-wrap items-end gap-2" onSubmit={submit}>
                        <label className="grid gap-1 text-sm">
                            <span className="text-muted-foreground">Desde</span>
                            <input
                                className="h-9 rounded-md border bg-background px-2"
                                type="date"
                                value={from}
                                onChange={(event) => setFrom(event.target.value)}
                            />
                        </label>
                        <label className="grid gap-1 text-sm">
                            <span className="text-muted-foreground">Hasta</span>
                            <input
                                className="h-9 rounded-md border bg-background px-2"
                                type="date"
                                value={to}
                                onChange={(event) => setTo(event.target.value)}
                            />
                        </label>
                        <button className="h-9 rounded-md border px-3 text-sm font-medium" type="submit">
                            Filtrar
                        </button>
                        <a className="h-9 rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground" href={exportHref}>
                            Exportar Excel
                        </a>
                    </form>
                </header>

                <section className="grid gap-3 sm:grid-cols-2">
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">Recibos</p>
                        <p className="text-2xl font-semibold">{totals.receipts}</p>
                    </div>
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">Total externo</p>
                        <p className="text-2xl font-semibold">${totals.total_pesos.toLocaleString('es-MX')}</p>
                    </div>
                </section>

                <section className="overflow-hidden rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted text-left">
                            <tr>
                                <th className="px-3 py-2">Folio</th>
                                <th className="px-3 py-2">Fecha</th>
                                <th className="px-3 py-2">Estudiante</th>
                                <th className="px-3 py-2">Concepto</th>
                                <th className="px-3 py-2">Depósito SEQ</th>
                                <th className="px-3 py-2 text-right">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <Link href={`/finance/receipts/${row.id}`}>{row.folio}</Link>
                                    </td>
                                    <td className="px-3 py-2">
                                        {row.issued_at ? new Date(row.issued_at).toLocaleDateString() : ''}
                                    </td>
                                    <td className="px-3 py-2">
                                        {row.student_name} · {row.grade}
                                        {row.group}
                                    </td>
                                    <td className="px-3 py-2">{row.concept_name}</td>
                                    <td className="px-3 py-2">
                                        {row.seq_deposit ? (
                                            <div>
                                                <div className="font-medium">
                                                    {row.seq_deposit.bank_transaction_folio}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {row.seq_deposit.deposit_date} ·{' '}
                                                    {row.seq_deposit.deposit_type}
                                                </div>
                                            </div>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                Pendiente
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 text-right">${row.total_pesos.toLocaleString('es-MX')}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            </main>
        </>
    );
}
