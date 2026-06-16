import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, FilePlus2, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import {
    money,
    ProcedureStatusBadge,
} from '@/components/finance/finance-badges';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import finance from '@/routes/finance';

type Procedure = {
    id: number;
    folio: string | null;
    student_name: string;
    status: string;
    total_pesos: number;
    created_at: string | null;
};

type FilterOption = {
    id: number;
    name: string;
};

type StatusOption = {
    value: string;
    label: string;
};

type Props = {
    filters: {
        procedure_type: string | null;
        date: string | null;
        student_name: string | null;
        status: string | null;
    };
    filter_options: {
        procedure_types: FilterOption[];
        statuses: StatusOption[];
    };
    procedures: {
        data: Procedure[];
    };
};

const statusLabels: Record<string, string> = {
    draft: 'Borrador',
    pending_payment: 'Pendiente de pago',
    paid: 'Pagado',
    cancelled: 'Cancelado',
};

function formatDateTime(value: string | null) {
    if (!value) {
        return 'Sin fecha';
    }

    return new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export default function PaymentProcedureIndex({
    filters,
    filter_options,
    procedures,
}: Props) {
    const [procedureType, setProcedureType] = useState(
        filters.procedure_type ?? '',
    );
    const [date, setDate] = useState(filters.date ?? '');
    const [studentName, setStudentName] = useState(filters.student_name ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        router.get(
            finance.paymentProcedures.index().url,
            {
                procedure_type: procedureType || undefined,
                date: date || undefined,
                student_name: studentName || undefined,
                status: status || undefined,
            },
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    }

    function clearFilters() {
        setProcedureType('');
        setDate('');
        setStudentName('');
        setStatus('');

        router.get(
            finance.paymentProcedures.index().url,
            {},
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    }

    return (
        <>
            <Head title="Trámites de pago" />
            <main className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Caja y recibos
                        </p>
                        <h1 className="text-xl font-semibold">
                            Trámites de pago
                        </h1>
                    </div>
                    <Button asChild>
                        <Link href={finance.paymentProcedures.create()}>
                            <FilePlus2 className="size-4" />
                            Nuevo trámite
                        </Link>
                    </Button>
                </header>
                <form
                    className="flex flex-wrap items-end gap-2 rounded-lg border bg-card p-3"
                    onSubmit={submit}
                >
                    <label className="grid min-w-52 flex-1 gap-1 text-sm">
                        <span className="text-muted-foreground">
                            Tipo de trámite
                        </span>
                        <select
                            className="h-9 rounded-md border bg-background px-3"
                            value={procedureType}
                            onChange={(event) =>
                                setProcedureType(event.target.value)
                            }
                        >
                            <option value="">Todos</option>
                            {filter_options.procedure_types.map((option) => (
                                <option key={option.id} value={option.id}>
                                    {option.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="grid gap-1 text-sm">
                        <span className="text-muted-foreground">Fecha</span>
                        <Input
                            className="w-40"
                            type="date"
                            value={date}
                            onChange={(event) => setDate(event.target.value)}
                        />
                    </label>
                    <label className="grid min-w-52 flex-1 gap-1 text-sm">
                        <span className="text-muted-foreground">
                            Estudiante
                        </span>
                        <Input
                            placeholder="Nombre del estudiante"
                            value={studentName}
                            onChange={(event) =>
                                setStudentName(event.target.value)
                            }
                        />
                    </label>
                    <label className="grid gap-1 text-sm">
                        <span className="text-muted-foreground">Estado</span>
                        <select
                            className="h-9 rounded-md border bg-background px-3"
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                        >
                            <option value="">Todos</option>
                            {filter_options.statuses.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {statusLabels[option.value] ?? option.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <Button type="submit" variant="outline">
                        Filtrar
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={clearFilters}
                    >
                        <RotateCcw className="size-4" />
                        Limpiar
                    </Button>
                </form>
                <section className="overflow-x-auto rounded-lg border">
                    <table className="min-w-[42rem] w-full text-sm">
                        <thead className="bg-muted text-left">
                            <tr>
                                <th className="px-3 py-2">Estudiante</th>
                                <th className="px-3 py-2">Fecha/hora</th>
                                <th className="px-3 py-2">Estado</th>
                                <th className="px-3 py-2 text-right">Total</th>
                                <th className="px-3 py-2 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            {procedures.data.length > 0 ? (
                                procedures.data.map((procedure) => (
                                    <tr
                                        key={procedure.id}
                                        className="border-t transition-colors hover:bg-muted/50"
                                    >
                                        <td className="px-3 py-2">
                                            <Link
                                                className="font-medium hover:underline"
                                                href={finance.paymentProcedures.show(
                                                    procedure.id,
                                                )}
                                            >
                                                {procedure.student_name}
                                            </Link>
                                            {procedure.folio ? (
                                                <div className="text-xs text-muted-foreground">
                                                    {procedure.folio}
                                                </div>
                                            ) : null}
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {formatDateTime(
                                                procedure.created_at,
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            <ProcedureStatusBadge
                                                status={procedure.status}
                                            />
                                        </td>
                                        <td className="px-3 py-2 text-right font-medium tabular-nums">
                                            {money(procedure.total_pesos)}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link
                                                    href={finance.paymentProcedures.show(
                                                        procedure.id,
                                                    )}
                                                >
                                                    Continuar
                                                    <ArrowRight className="size-4" />
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr className="border-t">
                                    <td
                                        className="px-3 py-8 text-center text-muted-foreground"
                                        colSpan={5}
                                    >
                                        No hay trámites con esos filtros.
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
