import { Head, Link } from '@inertiajs/react';
import { FileUp, FolderOpen } from 'lucide-react';
import { Button } from '@/components/ui/button';
import finance from '@/routes/finance';

type Program = {
    id: number;
    fiscal_year: number;
    name: string;
    requested_total_cents: number;
    approved_total_cents: number;
    federal_authorized_total_cents: number | null;
    adjusted_total_cents: number;
    created_at: string | null;
};

type Props = {
    programs: Program[];
};

function money(cents: number): string {
    return (cents / 100).toLocaleString('es-MX', {
        style: 'currency',
        currency: 'MXN',
    });
}

function moneyOrPending(cents: number | null): string {
    return cents === null ? 'Pendiente' : money(cents);
}

export default function U300ProgramsIndex({ programs }: Props) {
    return (
        <>
            <Head title="Presupuesto U300" />
            <main className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <header className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            PROFEXCE
                        </p>
                        <h1 className="text-xl font-semibold">
                            Presupuesto U300
                        </h1>
                    </div>
                    <Button asChild>
                        <Link href={finance.u300.imports.create()}>
                            <FileUp className="size-4" />
                            Importar proyecto
                        </Link>
                    </Button>
                </header>

                <section className="overflow-hidden rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/60 text-left">
                            <tr>
                                <th className="px-3 py-2">Proyecto</th>
                                <th className="px-3 py-2">Año</th>
                                <th className="px-3 py-2 text-right">
                                    Solicitado
                                </th>
                                <th className="px-3 py-2 text-right">
                                    Dictaminado
                                </th>
                                <th className="px-3 py-2 text-right">
                                    Autorizado federal
                                </th>
                                <th className="px-3 py-2 text-right">
                                    Adecuado
                                </th>
                                <th className="px-3 py-2 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            {programs.map((program) => (
                                <tr key={program.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <p className="font-medium">
                                            {program.name}
                                        </p>
                                        {program.created_at && (
                                            <p className="text-xs text-muted-foreground">
                                                Importado {program.created_at}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-3 py-2">
                                        {program.fiscal_year}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {money(program.requested_total_cents)}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {money(program.approved_total_cents)}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {moneyOrPending(
                                            program.federal_authorized_total_cents,
                                        )}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {money(program.adjusted_total_cents)}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={finance.u300.programs.show(
                                                    program,
                                                )}
                                            >
                                                <FolderOpen className="size-4" />
                                                Abrir
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {programs.length === 0 && (
                                <tr>
                                    <td
                                        className="px-3 py-8 text-center text-muted-foreground"
                                        colSpan={7}
                                    >
                                        Sin proyectos U300 importados
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
