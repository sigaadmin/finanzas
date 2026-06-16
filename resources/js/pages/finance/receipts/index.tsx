import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';

type Receipt = {
    id: number;
    folio: string;
    type: string;
    status: string;
    total_pesos: number;
    issued_at: string | null;
    student_name: string;
    concept_name: string | null;
};

type Props = {
    filters: {
        search: string | null;
        type: string | null;
        status: string | null;
    };
    receipts: {
        data: Receipt[];
    };
    options: {
        types: string[];
        statuses: string[];
    };
};

export default function ReceiptIndex({ filters, receipts, options }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [type, setType] = useState(filters.type ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        router.get(
            '/finance/receipts',
            {
                search: search || undefined,
                type: type || undefined,
                status: status || undefined,
            },
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    }

    return (
        <>
            <Head title="Recibos" />
            <main className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-sm text-muted-foreground">Comprobantes emitidos</p>
                        <h1 className="text-xl font-semibold">Recibos</h1>
                    </div>
                    <form className="flex flex-wrap items-end gap-2" onSubmit={submit}>
                        <label className="grid gap-1 text-sm">
                            <span className="text-muted-foreground">Búsqueda</span>
                            <input
                                className="h-9 rounded-md border bg-background px-2"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                            />
                        </label>
                        <label className="grid gap-1 text-sm">
                            <span className="text-muted-foreground">Tipo</span>
                            <select
                                className="h-9 rounded-md border bg-background px-2"
                                value={type}
                                onChange={(event) => setType(event.target.value)}
                            >
                                <option value="">Todos</option>
                                {options.types.map((option) => (
                                    <option key={option} value={option}>
                                        {option}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="grid gap-1 text-sm">
                            <span className="text-muted-foreground">Estado</span>
                            <select
                                className="h-9 rounded-md border bg-background px-2"
                                value={status}
                                onChange={(event) => setStatus(event.target.value)}
                            >
                                <option value="">Todos</option>
                                {options.statuses.map((option) => (
                                    <option key={option} value={option}>
                                        {option}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <button className="h-9 rounded-md border px-3 text-sm font-medium" type="submit">
                            Filtrar
                        </button>
                    </form>
                </header>

                <section className="overflow-hidden rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted text-left">
                            <tr>
                                <th className="px-3 py-2">Folio</th>
                                <th className="px-3 py-2">Estudiante</th>
                                <th className="px-3 py-2">Tipo</th>
                                <th className="px-3 py-2">Estado</th>
                                <th className="px-3 py-2">Concepto</th>
                                <th className="px-3 py-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {receipts.data.map((receipt) => (
                                <tr key={receipt.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <Link href={`/finance/receipts/${receipt.id}`}>{receipt.folio}</Link>
                                    </td>
                                    <td className="px-3 py-2">{receipt.student_name}</td>
                                    <td className="px-3 py-2">{receipt.type}</td>
                                    <td className="px-3 py-2">{receipt.status}</td>
                                    <td className="px-3 py-2">{receipt.concept_name ?? 'Trámite completo'}</td>
                                    <td className="px-3 py-2 text-right">${receipt.total_pesos.toLocaleString('es-MX')}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            </main>
        </>
    );
}
