import { Head, Link } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronRight,
    ClipboardList,
    Download,
    ListTree,
    TableProperties,
    Scale,
    SlidersHorizontal,
    WalletCards,
} from 'lucide-react';
import { Fragment, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import finance from '@/routes/finance';

type Props = {
    program: {
        id: number;
        fiscal_year: number;
        name: string;
        requested_total_cents: number;
        projects_count: number;
        goals_count: number;
        actions_count: number;
        summary: {
            approved_total_cents: number;
            federal_authorized_total_cents: number | null;
            adjusted_total_cents: number;
            executed_cents: number;
            available_cents: number;
            lines_count: number;
            lines_without_cog_count: number;
            lines_without_technical_sheet_count: number;
            active_movements_count: number;
            cancelled_movements_count: number;
        };
        lines: Array<{
            id: number;
            action_number: string;
            action_name: string;
            cog_code: string | null;
            cog_name: string | null;
            exercise_month: string | null;
            amount_cents: number;
            status: string;
        }>;
        actions: Array<{
            action_number: string;
            action_name: string;
            amount_cents: number;
            executed_cents: number;
            available_cents: number;
            status: string;
            cog_lines: Array<{
                id: number;
                cog_code: string | null;
                cog_name: string | null;
                exercise_month: string | null;
                amount_cents: number;
                technical_sheet_url: string;
            }>;
        }>;
    };
};

function money(cents: number | null): string {
    return ((cents ?? 0) / 100).toLocaleString('es-MX', {
        style: 'currency',
        currency: 'MXN',
    });
}

function moneyOrPending(cents: number | null): string {
    return cents === null ? 'Pendiente' : money(cents);
}

function statusVariant(status: string): 'default' | 'secondary' | 'outline' {
    return status === 'Completa' ? 'secondary' : 'outline';
}

export default function U300ProgramShow({ program }: Props) {
    const [expandedActions, setExpandedActions] = useState<Set<string>>(
        () => new Set(),
    );

    function toggleAction(actionNumber: string): void {
        setExpandedActions((current) => {
            const next = new Set(current);

            if (next.has(actionNumber)) {
                next.delete(actionNumber);
            } else {
                next.add(actionNumber);
            }

            return next;
        });
    }

    return (
        <>
            <Head title="Proyecto U300" />
            <main className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <header className="grid gap-3">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Presupuesto U300 · {program.fiscal_year}
                        </p>
                        <h1 className="text-xl leading-7 font-semibold">
                            {program.name}
                        </h1>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <a
                                href={
                                    finance.u300.programs.summary.export(
                                        program,
                                    ).url
                                }
                            >
                                <Download className="size-4" />
                                Exportar CSV
                            </a>
                        </Button>
                        <Button asChild variant="outline">
                            <a
                                href={
                                    finance.u300.programs.summary.exportXlsx(
                                        program,
                                    ).url
                                }
                            >
                                <Download className="size-4" />
                                Exportar Excel
                            </a>
                        </Button>
                        <Button asChild variant="outline">
                            <Link
                                href={finance.u300.programs.verdict.edit(
                                    program,
                                )}
                            >
                                <Scale className="size-4" />
                                Capturar veredicto
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link
                                href={finance.u300.programs.adjustment.edit(
                                    program,
                                )}
                            >
                                <SlidersHorizontal className="size-4" />
                                Adecuar presupuesto
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link
                                href={finance.u300.programs.cog.edit(program)}
                            >
                                <ListTree className="size-4" />
                                Convertir a COG
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link
                                href={finance.u300.programs.technicalSheets.edit(
                                    program,
                                )}
                            >
                                <ClipboardList className="size-4" />
                                Fichas técnicas
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link
                                href={finance.u300.programs.financialReports.show(
                                    program,
                                )}
                            >
                                <TableProperties className="size-4" />
                                Reportes
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link
                                href={finance.u300.programs.execution.index(
                                    program,
                                )}
                            >
                                <WalletCards className="size-4" />
                                Ejercicio
                            </Link>
                        </Button>
                    </div>
                </header>

                <section className="grid gap-3 md:grid-cols-5">
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
                            {money(program.summary.approved_total_cents)}
                        </p>
                    </div>
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">
                            Autorizado federal
                        </p>
                        <p className="mt-2 text-xl font-semibold">
                            {moneyOrPending(
                                program.summary.federal_authorized_total_cents,
                            )}
                        </p>
                    </div>
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">
                            Adecuado
                        </p>
                        <p className="mt-2 text-xl font-semibold">
                            {money(program.summary.adjusted_total_cents)}
                        </p>
                    </div>
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">
                            Disponible
                        </p>
                        <p className="mt-2 text-xl font-semibold">
                            {money(program.summary.available_cents)}
                        </p>
                    </div>
                </section>

                <section className="grid gap-3 md:grid-cols-4">
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">
                            Ejercido / comprometido
                        </p>
                        <p className="mt-2 text-xl font-semibold">
                            {money(program.summary.executed_cents)}
                        </p>
                    </div>
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">
                            Partidas sin COG
                        </p>
                        <p className="mt-2 text-xl font-semibold">
                            {program.summary.lines_without_cog_count}
                        </p>
                    </div>
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">
                            Partidas sin ficha
                        </p>
                        <p className="mt-2 text-xl font-semibold">
                            {
                                program.summary
                                    .lines_without_technical_sheet_count
                            }
                        </p>
                    </div>
                    <div className="rounded-lg border p-4">
                        <p className="text-sm text-muted-foreground">
                            Movimientos
                        </p>
                        <p className="mt-2 text-xl font-semibold">
                            {program.summary.active_movements_count}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {program.summary.cancelled_movements_count}{' '}
                            cancelados
                        </p>
                    </div>
                </section>

                <section className="overflow-hidden rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2">Acción</th>
                                <th className="px-3 py-2 text-right">
                                    Partidas
                                </th>
                                <th className="px-3 py-2 text-right">Monto</th>
                                <th className="px-3 py-2">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            {program.actions.map((action) => (
                                <Fragment key={action.action_number}>
                                    <tr className="border-t align-top">
                                        <td className="px-3 py-2">
                                            <button
                                                type="button"
                                                className="flex w-full items-start gap-2 text-left"
                                                onClick={() =>
                                                    toggleAction(
                                                        action.action_number,
                                                    )
                                                }
                                                aria-expanded={expandedActions.has(
                                                    action.action_number,
                                                )}
                                            >
                                                {expandedActions.has(
                                                    action.action_number,
                                                ) ? (
                                                    <ChevronDown className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                                ) : (
                                                    <ChevronRight className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                                )}
                                                <span>
                                                    <span className="font-medium">
                                                        {action.action_number}
                                                    </span>{' '}
                                                    {action.action_name}
                                                </span>
                                            </button>
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            {action.cog_lines.length}
                                        </td>
                                        <td className="px-3 py-2 text-right">
                                            {money(action.amount_cents)}
                                        </td>
                                        <td className="px-3 py-2">
                                            <Badge
                                                variant={statusVariant(
                                                    action.status,
                                                )}
                                            >
                                                {action.status}
                                            </Badge>
                                        </td>
                                    </tr>
                                    {expandedActions.has(
                                        action.action_number,
                                    ) && (
                                        <tr className="border-t bg-muted/20">
                                            <td colSpan={4} className="p-0">
                                                <table className="w-full text-xs">
                                                    <thead className="text-left text-muted-foreground">
                                                        <tr>
                                                            <th className="py-2 pr-3 pl-10">
                                                                Clave
                                                            </th>
                                                            <th className="px-3 py-2">
                                                                Partida
                                                            </th>
                                                            <th className="px-3 py-2">
                                                                Mes
                                                            </th>
                                                            <th className="px-3 py-2 text-right">
                                                                Monto
                                                            </th>
                                                            <th className="px-3 py-2 text-right">
                                                                Ficha
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {action.cog_lines.map(
                                                            (line) => (
                                                                <tr
                                                                    key={
                                                                        line.id
                                                                    }
                                                                    className="border-t"
                                                                >
                                                                    <td className="py-2 pr-3 pl-10 font-medium">
                                                                        {line.cog_code ??
                                                                            'Sin COG'}
                                                                    </td>
                                                                    <td className="px-3 py-2">
                                                                        {line.cog_name ??
                                                                            'Partida pendiente de clasificar'}
                                                                    </td>
                                                                    <td className="px-3 py-2">
                                                                        {line.exercise_month ??
                                                                            'Sin mes'}
                                                                    </td>
                                                                    <td className="px-3 py-2 text-right">
                                                                        {money(
                                                                            line.amount_cents,
                                                                        )}
                                                                    </td>
                                                                    <td className="px-3 py-2 text-right">
                                                                        <Link
                                                                            className="text-primary underline-offset-4 hover:underline"
                                                                            href={
                                                                                line.technical_sheet_url
                                                                            }
                                                                        >
                                                                            Abrir
                                                                        </Link>
                                                                    </td>
                                                                </tr>
                                                            ),
                                                        )}
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                    )}
                                </Fragment>
                            ))}
                            {program.actions.length === 0 && (
                                <tr>
                                    <td
                                        className="px-3 py-8 text-center text-muted-foreground"
                                        colSpan={4}
                                    >
                                        Sin adecuación presupuestal
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
