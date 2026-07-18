import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    FileCheck2,
    LockKeyhole,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import budgets from '@/routes/finance/own-revenue/budgets';
import annualClose from '@/routes/finance/own-revenue/budgets/annual-close';

type Snapshot = {
    balances: {
        initial_amount_cents: string;
        modified_amount_cents: string;
        reserved_amount_cents: string;
        committed_amount_cents: string;
        paid_amount_cents: string;
        available_amount_cents: string;
    };
    expense_dossiers: {
        total: number;
        pending_requirements: number;
    };
    fuel: {
        acquired_amount_cents: string;
        confirmed_consumption_cents: string;
        pending_needs_cents: string;
        available_amount_cents: string;
    };
    modifications: {
        count: number;
        transfer_amount_cents: string;
        rescheduling_amount_cents: string;
    };
    official_exports_count: number;
};

type Props = {
    budget: {
        id: number;
        fiscal_year: number;
        status: string;
        region_code: string;
        region_name: string;
    };
    review: {
        eligible: boolean;
        state_is_eligible: boolean;
        confirmation_phrase: string;
        blockers: Array<{ type: string; count: number; message: string }>;
        snapshot: Snapshot;
    };
    closure: {
        id: number;
        note: string;
        snapshot: Snapshot;
        fingerprint: string;
        closed_by: { id: number; name: string };
        closed_at: string | null;
    } | null;
    permissions: { close: boolean };
};

type CloseForm = {
    confirmation: string;
    note: string;
};

function formatCents(value: string): string {
    const cents = BigInt(value || '0');
    const sign = cents < 0n ? '-' : '';
    const absolute = cents < 0n ? -cents : cents;
    const pesos = absolute / 100n;
    const fraction = (absolute % 100n).toString().padStart(2, '0');

    return `${sign}$${pesos.toLocaleString('es-MX')}.${fraction}`;
}

function formatDate(value: string | null): string {
    if (value === null) {
        return 'Fecha no disponible';
    }

    return new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'long',
        timeStyle: 'short',
        timeZone: 'America/Cancun',
    }).format(new Date(value));
}

export default function OwnRevenueAnnualCloseShow({
    budget,
    review,
    closure,
    permissions,
}: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const form = useForm<CloseForm>({ confirmation: '', note: '' });
    const snapshot = closure?.snapshot ?? review.snapshot;
    const canSubmit =
        review.eligible &&
        permissions.close &&
        form.data.confirmation === review.confirmation_phrase &&
        form.data.note.trim().length >= 10 &&
        form.data.note.trim().length <= 1000 &&
        !form.processing;

    const submit = (): void => {
        if (!canSubmit) {
            return;
        }

        form.post(annualClose.store.url(budget.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setDialogOpen(false);
            },
        });
    };

    return (
        <>
            <Head title={`Cierre anual ${budget.fiscal_year}`} />
            <main className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="grid gap-3">
                    <Button asChild variant="ghost" size="sm" className="w-fit">
                        <Link href={budgets.show(budget.id)}>
                            <ArrowLeft className="size-4" /> Volver al
                            presupuesto
                        </Link>
                    </Button>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Región {budget.region_code} ·{' '}
                                {budget.region_name}
                            </p>
                            <h1 className="mt-1 flex items-center gap-2 text-2xl font-semibold">
                                <LockKeyhole className="size-6" /> Cierre anual{' '}
                                {budget.fiscal_year}
                            </h1>
                        </div>
                        <Badge
                            variant="outline"
                            className={
                                closure === null
                                    ? 'w-fit border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200'
                                    : 'w-fit border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200'
                            }
                        >
                            {closure === null ? 'En revisión' : 'Cerrado'}
                        </Badge>
                    </div>
                </header>

                {closure !== null ? (
                    <Card className="border-emerald-300 dark:border-emerald-800">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileCheck2 className="size-5 text-emerald-600" />
                                Acta de cierre anual
                            </CardTitle>
                            <CardDescription>
                                Registro definitivo emitido el{' '}
                                {formatDate(closure.closed_at)}.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-5">
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Detail
                                    label="Responsable del cierre"
                                    value={closure.closed_by.name}
                                />
                                <Detail
                                    label="Huella de integridad"
                                    value={closure.fingerprint}
                                    mono
                                />
                            </dl>
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Nota institucional
                                </p>
                                <p className="mt-1 whitespace-pre-wrap">
                                    {closure.note}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <CloseReadiness
                        review={review}
                        permissions={permissions}
                        dialogOpen={dialogOpen}
                        setDialogOpen={setDialogOpen}
                        form={form}
                        canSubmit={canSubmit}
                        submit={submit}
                    />
                )}

                <SnapshotCards snapshot={snapshot} />
            </main>
        </>
    );
}

function CloseReadiness({
    review,
    permissions,
    dialogOpen,
    setDialogOpen,
    form,
    canSubmit,
    submit,
}: {
    review: Props['review'];
    permissions: Props['permissions'];
    dialogOpen: boolean;
    setDialogOpen: (open: boolean) => void;
    form: ReturnType<typeof useForm<CloseForm>>;
    canSubmit: boolean;
    submit: () => void;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Revisión previa</CardTitle>
                <CardDescription>
                    El cierre conserva esta fotografía del ejercicio y no
                    permite registrar operaciones posteriores.
                </CardDescription>
            </CardHeader>
            <CardContent className="grid gap-4">
                {!review.state_is_eligible && (
                    <Alert>
                        <AlertTriangle />
                        <AlertTitle>
                            El ejercicio todavía no puede cerrarse
                        </AlertTitle>
                        <AlertDescription>
                            Primero debe contar con un presupuesto inicial
                            autorizado y encontrarse en ejecución.
                        </AlertDescription>
                    </Alert>
                )}

                {review.blockers.length > 0 && (
                    <Alert>
                        <AlertTriangle />
                        <AlertTitle>Hay asuntos por concluir</AlertTitle>
                        <AlertDescription>
                            <ul className="list-disc space-y-1 pl-5">
                                {review.blockers.map((blocker) => (
                                    <li key={blocker.type}>
                                        {blocker.message}
                                    </li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                )}

                {review.eligible && (
                    <Alert className="border-emerald-300 dark:border-emerald-800">
                        <CheckCircle2 className="text-emerald-600" />
                        <AlertTitle>Revisión concluida</AlertTitle>
                        <AlertDescription>
                            No hay operaciones pendientes que impidan cerrar el
                            ejercicio.
                        </AlertDescription>
                    </Alert>
                )}

                <div>
                    <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                        <DialogTrigger asChild>
                            <Button
                                type="button"
                                disabled={
                                    !review.eligible || !permissions.close
                                }
                            >
                                <LockKeyhole className="size-4" /> Cerrar
                                ejercicio
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Cierre definitivo</DialogTitle>
                                <DialogDescription>
                                    Confirma únicamente después de revisar los
                                    saldos y movimientos. Esta acción es
                                    permanente.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="close-confirmation">
                                        Escribe{' '}
                                        <span className="font-mono font-semibold">
                                            {review.confirmation_phrase}
                                        </span>
                                    </Label>
                                    <Input
                                        id="close-confirmation"
                                        value={form.data.confirmation}
                                        onChange={(event) =>
                                            form.setData(
                                                'confirmation',
                                                event.target.value,
                                            )
                                        }
                                        autoComplete="off"
                                    />
                                    <InputError
                                        message={form.errors.confirmation}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="close-note">
                                        Nota institucional del cierre
                                    </Label>
                                    <textarea
                                        id="close-note"
                                        value={form.data.note}
                                        onChange={(event) =>
                                            form.setData(
                                                'note',
                                                event.target.value,
                                            )
                                        }
                                        maxLength={1000}
                                        rows={5}
                                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    />
                                    <div className="flex justify-between gap-3 text-xs text-muted-foreground">
                                        <span>Mínimo 10 caracteres</span>
                                        <span>
                                            {form.data.note.length} / 1000
                                        </span>
                                    </div>
                                    <InputError message={form.errors.note} />
                                </div>
                            </div>
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button type="button" variant="outline">
                                        Cancelar
                                    </Button>
                                </DialogClose>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    disabled={!canSubmit}
                                    onClick={submit}
                                >
                                    {form.processing
                                        ? 'Cerrando…'
                                        : 'Confirmar cierre definitivo'}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </div>
            </CardContent>
        </Card>
    );
}

function SnapshotCards({ snapshot }: { snapshot: Snapshot }) {
    const cards = [
        ['Inicial', formatCents(snapshot.balances.initial_amount_cents)],
        ['Modificado', formatCents(snapshot.balances.modified_amount_cents)],
        ['Pagado', formatCents(snapshot.balances.paid_amount_cents)],
        ['Disponible', formatCents(snapshot.balances.available_amount_cents)],
        [
            'Expedientes',
            snapshot.expense_dossiers.total.toLocaleString('es-MX'),
        ],
        [
            'Modificaciones',
            snapshot.modifications.count.toLocaleString('es-MX'),
        ],
        [
            'Combustible adquirido',
            formatCents(snapshot.fuel.acquired_amount_cents),
        ],
        [
            'Combustible disponible',
            formatCents(snapshot.fuel.available_amount_cents),
        ],
    ];

    return (
        <section aria-labelledby="close-snapshot-title" className="grid gap-3">
            <div>
                <h2 id="close-snapshot-title" className="text-xl font-semibold">
                    Fotografía financiera
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Saldos registrados al momento de consultar o formalizar el
                    cierre.
                </p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                {cards.map(([label, value]) => (
                    <Card key={label}>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm text-muted-foreground">
                                {label}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-lg font-semibold">
                            {value}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </section>
    );
}

function Detail({
    label,
    value,
    mono = false,
}: {
    label: string;
    value: string;
    mono?: boolean;
}) {
    return (
        <div>
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd
                className={`mt-1 font-medium break-all ${mono ? 'font-mono text-xs' : ''}`}
            >
                {value}
            </dd>
        </div>
    );
}
