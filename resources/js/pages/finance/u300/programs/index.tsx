import { Head, Link, useForm } from '@inertiajs/react';
import { FileUp, FolderOpen } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
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
    can_manage_backups: boolean;
    restore_preview: {
        token: string;
        fiscal_year: number;
        files_count: number;
    } | null;
    backup_operations: {
        id: number;
        fiscal_year: number;
        type: string;
        status: string;
        created_at: string | null;
        performed_by: string | null;
    }[];
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

export default function U300ProgramsIndex({
    programs,
    can_manage_backups,
    restore_preview,
    backup_operations,
}: Props) {
    const upload = useForm<{ archive: File | null }>({ archive: null });
    const restore = useForm({
        preview_token: restore_preview?.token ?? '',
        confirmation: '',
    });

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
                    <div className="flex gap-2">
                        <Button asChild>
                            <Link href={finance.u300.imports.create()}>
                                <FileUp className="size-4" />
                                Importar proyecto
                            </Link>
                        </Button>
                        {can_manage_backups && (
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button variant="outline">
                                        Restaurar respaldo
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>
                                            Restaurar U300
                                        </DialogTitle>
                                        <DialogDescription>
                                            El paquete reemplazará por completo
                                            el U300 del año incluido.
                                        </DialogDescription>
                                    </DialogHeader>
                                    {restore_preview ? (
                                        <form
                                            onSubmit={(event) => {
                                                event.preventDefault();
                                                restore
                                                    .transform((data) => ({
                                                        ...data,
                                                        preview_token:
                                                            restore_preview.token,
                                                    }))
                                                    .post(
                                                        finance.u300.backups.restore()
                                                            .url,
                                                    );
                                            }}
                                            className="space-y-3"
                                        >
                                            <p>
                                                Respaldo{' '}
                                                {restore_preview.fiscal_year}:{' '}
                                                {restore_preview.files_count}{' '}
                                                archivos.
                                            </p>
                                            <Input
                                                value={
                                                    restore.data.confirmation
                                                }
                                                onChange={(event) =>
                                                    restore.setData(
                                                        'confirmation',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder={`RESTAURAR U300 ${restore_preview.fiscal_year}`}
                                            />
                                            <InputError
                                                message={
                                                    restore.errors.confirmation
                                                }
                                            />
                                            <InputError
                                                message={
                                                    restore.errors.preview_token
                                                }
                                            />
                                            <DialogFooter>
                                                <Button
                                                    type="submit"
                                                    variant="destructive"
                                                    disabled={
                                                        restore.processing ||
                                                        restore.data
                                                            .confirmation !==
                                                            `RESTAURAR U300 ${restore_preview.fiscal_year}`
                                                    }
                                                >
                                                    Restaurar
                                                </Button>
                                            </DialogFooter>
                                        </form>
                                    ) : (
                                        <form
                                            onSubmit={(event) => {
                                                event.preventDefault();
                                                upload.post(
                                                    finance.u300.backups.preview()
                                                        .url,
                                                    { forceFormData: true },
                                                );
                                            }}
                                            className="space-y-3"
                                        >
                                            <Input
                                                type="file"
                                                accept=".zip,application/zip"
                                                onChange={(event) =>
                                                    upload.setData(
                                                        'archive',
                                                        event.target
                                                            .files?.[0] ?? null,
                                                    )
                                                }
                                            />
                                            <InputError
                                                message={upload.errors.archive}
                                            />
                                            <DialogFooter>
                                                <Button
                                                    type="submit"
                                                    disabled={
                                                        upload.processing ||
                                                        upload.data.archive ===
                                                            null
                                                    }
                                                >
                                                    Validar respaldo
                                                </Button>
                                            </DialogFooter>
                                        </form>
                                    )}
                                </DialogContent>
                            </Dialog>
                        )}
                    </div>
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
                                        {can_manage_backups && (
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="ghost"
                                            >
                                                <a
                                                    href={finance.u300.programs.backups.download.url(
                                                        program,
                                                    )}
                                                >
                                                    Respaldar
                                                </a>
                                            </Button>
                                        )}
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
                {can_manage_backups && (
                    <section className="rounded-lg border p-3 text-sm">
                        <h2 className="font-medium">Bitácora de respaldos</h2>
                        <ul className="mt-2 space-y-1 text-muted-foreground">
                            {backup_operations.map((operation) => (
                                <li key={operation.id}>
                                    {operation.created_at ?? '—'} ·{' '}
                                    {operation.fiscal_year} · {operation.type} ·{' '}
                                    {operation.status} ·{' '}
                                    {operation.performed_by ?? '—'}
                                </li>
                            ))}
                            {backup_operations.length === 0 && (
                                <li>Sin operaciones registradas</li>
                            )}
                        </ul>
                    </section>
                )}
            </main>
        </>
    );
}
