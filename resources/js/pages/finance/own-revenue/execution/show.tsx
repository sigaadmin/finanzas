import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRightLeft, CalendarClock, Download, FileText, Plus } from 'lucide-react';
import type { FormEvent} from 'react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import budgets from '@/routes/finance/own-revenue/budgets';
import expenseDossierRoutes from '@/routes/finance/own-revenue/budgets/execution/expense-dossiers';
import modifications from '@/routes/finance/own-revenue/budgets/execution/modifications';
import planning from '@/routes/finance/own-revenue/budgets/planning';
import expenseDossierDocuments from '@/routes/finance/own-revenue/expense-dossier-documents';
import type {
    BudgetModification,
    ExecutionBudget,
    ExecutionClassification,
    ExecutionLine,
    ExecutionSummary,
    ExpenseDossier,
} from '@/types/finance-own-revenue-execution';

type Props = {
    budget: ExecutionBudget;
    summary: ExecutionSummary;
    lines: ExecutionLine[];
    classifications: ExecutionClassification[];
    modifications: BudgetModification[];
    expense_dossiers: ExpenseDossier[];
    permissions: {
        manage: boolean;
        create_expense_dossier: boolean;
        request_expense_sufficiency: boolean;
        confirm_expense_sufficiency: boolean;
        manage_expense_purchase: boolean;
        authorize_expense_payment: boolean;
    };
};

type ModificationForm = {
    type: 'transfer' | 'rescheduling';
    source_line_id: string;
    destination_expense_classification_id: string;
    destination_month: string;
    amount_pesos: string;
    amount_cents: string;
    reason: string;
};

type ExpenseDossierForm = {
    own_revenue_modified_budget_line_id: string;
    concept: string;
    amount_pesos: string;
    amount_cents: string;
    purchase_responsibility: 'cren' | 'seq';
    external_reference: string;
    notes: string;
};

type PaymentRequestForm = {
    payment_request_reference: string;
    documents: File[];
};

type AuthorizationAction = {
    dossier: ExpenseDossier;
    kind: 'finance' | 'budget-office' | 'payment';
};

const authorizationCopy = {
    finance: {
        title: 'Autorizar en Finanzas',
        description: 'Registra la referencia emitida por Finanzas para continuar el trámite.',
        label: 'Referencia de Finanzas',
        button: 'Registrar autorización',
    },
    'budget-office': {
        title: 'Autorizar en Presupuesto o Pagaduría',
        description: 'Registra la referencia externa que confirma la segunda autorización.',
        label: 'Referencia de Presupuesto o Pagaduría',
        button: 'Registrar autorización',
    },
    payment: {
        title: 'Registrar pago',
        description: 'Registra la transferencia, póliza o referencia que acredita el pago.',
        label: 'Referencia del pago',
        button: 'Marcar como pagado',
    },
} satisfies Record<AuthorizationAction['kind'], { title: string; description: string; label: string; button: string }>;

const dossierStatusLabels: Record<ExpenseDossier['status'], string> = {
    draft: 'Borrador',
    sufficiency_requested: 'Suficiencia solicitada',
    sufficiency_confirmed: 'Suficiencia confirmada',
    purchase_in_progress: 'Compra en proceso',
    payment_requested: 'Pago solicitado',
    finance_authorized: 'Autorizado por Finanzas',
    budget_office_authorized: 'Autorizado por Presupuesto',
    paid: 'Pagado',
    rejected: 'Rechazado',
    cancelled: 'Cancelado',
};

const months = [
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

const selectClassName =
    'border-input bg-background ring-offset-background focus-visible:ring-ring h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50';

function formatCents(value: string): string {
    const cents = BigInt(value || '0');
    const pesos = cents / 100n;
    const fraction = (cents % 100n).toString().padStart(2, '0');

    return `$${pesos.toLocaleString('es-MX')}.${fraction}`;
}

function pesosToCents(value: string): string | null {
    const normalized = value.trim().replace(/,/g, '');

    if (!/^\d+(?:\.\d{1,2})?$/.test(normalized)) {
        return null;
    }

    const [whole, fraction = ''] = normalized.split('.');

    return `${BigInt(whole) * 100n + BigInt(fraction.padEnd(2, '0'))}`;
}

function displayDate(value: string | null): string {
    if (value === null) {
        return 'Fecha no disponible';
    }

    return new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'America/Cancun',
    }).format(new Date(value));
}

export default function OwnRevenueExecutionShow({
    budget,
    summary,
    lines,
    classifications,
    modifications: history,
    expense_dossiers: expenseDossiers,
    permissions,
}: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [expenseDialogOpen, setExpenseDialogOpen] = useState(false);
    const [purchaseDossier, setPurchaseDossier] = useState<ExpenseDossier | null>(null);
    const [paymentDossier, setPaymentDossier] = useState<ExpenseDossier | null>(null);
    const [authorizationAction, setAuthorizationAction] = useState<AuthorizationAction | null>(null);
    const usableLines = lines.filter(
        (line) => BigInt(line.available_amount_cents) > 0n,
    );
    const form = useForm<ModificationForm>({
        type: 'transfer',
        source_line_id: usableLines[0]?.id.toString() ?? '',
        destination_expense_classification_id: '',
        destination_month: usableLines[0]?.month.toString() ?? '',
        amount_pesos: '',
        amount_cents: '',
        reason: '',
    });
    const expenseForm = useForm<ExpenseDossierForm>({
        own_revenue_modified_budget_line_id: usableLines[0]?.id.toString() ?? '',
        concept: '',
        amount_pesos: '',
        amount_cents: '',
        purchase_responsibility: 'cren',
        external_reference: '',
        notes: '',
    });
    const purchaseForm = useForm({ purchase_reference: '' });
    const paymentForm = useForm<PaymentRequestForm>({
        payment_request_reference: '',
        documents: [],
    });
    const authorizationForm = useForm({ reference: '' });
    const selectedSource = lines.find(
        (line) => line.id.toString() === form.data.source_line_id,
    );
    const destinationOptions = useMemo(() => {
        if (selectedSource === undefined) {
            return [];
        }

        return classifications.filter((classification) =>
            form.data.type === 'transfer'
                ? classification.chapter_code === selectedSource.chapter_code &&
                  classification.specific_item_code !==
                      selectedSource.specific_item_code
                : classification.specific_item_code ===
                  selectedSource.specific_item_code,
        );
    }, [classifications, form.data.type, selectedSource]);

    const resetDestination = (
        type: ModificationForm['type'],
        source: ExecutionLine | undefined,
    ): void => {
        const destinations = classifications.filter((classification) =>
            source === undefined
                ? false
                : type === 'transfer'
                  ? classification.chapter_code === source.chapter_code &&
                    classification.specific_item_code !==
                        source.specific_item_code
                  : classification.specific_item_code ===
                    source.specific_item_code,
        );
        form.setData((data) => ({
            ...data,
            type,
            source_line_id: source?.id.toString() ?? '',
            destination_expense_classification_id:
                destinations[0]?.id.toString() ?? '',
            destination_month:
                type === 'transfer' ? (source?.month.toString() ?? '') : '',
        }));
    };

    const openForm = (line?: ExecutionLine): void => {
        const source = line ?? usableLines[0];
        form.reset();
        form.clearErrors();
        resetDestination('transfer', source);
        setDialogOpen(true);
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        const cents = pesosToCents(form.data.amount_pesos);

        if (cents === null || cents === '0') {
            form.setError('amount_cents', 'Captura un importe válido mayor que cero.');

            return;
        }

        form.transform((data) => ({ ...data, amount_cents: cents }));
        form.post(modifications.store(budget.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                setDialogOpen(false);
                form.reset();
            },
        });
    };

    const openExpenseForm = (): void => {
        expenseForm.reset();
        expenseForm.clearErrors();
        expenseForm.setData('own_revenue_modified_budget_line_id', usableLines[0]?.id.toString() ?? '');
        setExpenseDialogOpen(true);
    };

    const submitExpense = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        const cents = pesosToCents(expenseForm.data.amount_pesos);

        if (cents === null || cents === '0') {
            expenseForm.setError('amount_cents', 'Captura un importe válido mayor que cero.');

            return;
        }

        expenseForm.transform((data) => ({ ...data, amount_cents: cents }));
        expenseForm.post(expenseDossierRoutes.store(budget.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                setExpenseDialogOpen(false);
                expenseForm.reset();
            },
        });
    };

    const advanceDossier = (dossier: ExpenseDossier, action: 'request' | 'confirm'): void => {
        const route = action === 'request'
            ? expenseDossierRoutes.sufficiencyRequest([budget.id, dossier.id])
            : expenseDossierRoutes.sufficiencyConfirmation([budget.id, dossier.id]);
        router.post(route.url, {}, { preserveScroll: true });
    };

    const openPurchaseForm = (dossier: ExpenseDossier): void => {
        purchaseForm.reset();
        purchaseForm.clearErrors();
        setPurchaseDossier(dossier);
    };

    const submitPurchase = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (purchaseDossier === null) {
            return;
        }

        purchaseForm.post(expenseDossierRoutes.purchaseStart([budget.id, purchaseDossier.id]).url, {
            preserveScroll: true,
            onSuccess: () => setPurchaseDossier(null),
        });
    };

    const openPaymentForm = (dossier: ExpenseDossier): void => {
        paymentForm.reset();
        paymentForm.clearErrors();
        setPaymentDossier(dossier);
    };

    const submitPayment = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (paymentDossier === null) {
            return;
        }

        paymentForm.post(expenseDossierRoutes.paymentRequest([budget.id, paymentDossier.id]).url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => setPaymentDossier(null),
        });
    };

    const openAuthorizationForm = (dossier: ExpenseDossier, kind: AuthorizationAction['kind']): void => {
        authorizationForm.reset();
        authorizationForm.clearErrors();
        setAuthorizationAction({ dossier, kind });
    };

    const submitAuthorization = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (authorizationAction === null) {
            return;
        }

        const { dossier, kind } = authorizationAction;
        const route = kind === 'finance'
            ? expenseDossierRoutes.financeAuthorization([budget.id, dossier.id])
            : kind === 'budget-office'
              ? expenseDossierRoutes.budgetOfficeAuthorization([budget.id, dossier.id])
              : expenseDossierRoutes.payment([budget.id, dossier.id]);
        const field = kind === 'finance'
            ? 'finance_authorization_reference'
            : kind === 'budget-office'
              ? 'budget_office_authorization_reference'
              : 'payment_reference';

        authorizationForm.transform((data) => ({ [field]: data.reference }));
        authorizationForm.post(route.url, {
            preserveScroll: true,
            onSuccess: () => setAuthorizationAction(null),
            onError: (errors) => authorizationForm.setError('reference', Object.values(errors)[0] ?? 'No fue posible registrar la referencia.'),
        });
    };

    return (
        <>
            <Head title={`Presupuesto modificado ${budget.fiscal_year}`} />
            <main className="flex h-full flex-1 flex-col gap-5 p-4 md:p-6">
                <header className="grid gap-3">
                    <Button asChild variant="ghost" size="sm" className="w-fit">
                        <Link href={budgets.show(budget.id)}>
                            <ArrowLeft className="size-4" />
                            Volver al ejercicio
                        </Link>
                    </Button>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Ingresos propios · {budget.region_code} {budget.region_name}
                            </p>
                            <h1 className="text-2xl font-semibold">
                                Presupuesto modificado {budget.fiscal_year}
                            </h1>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button asChild variant="outline">
                                <Link href={planning.show(budget.id)}>
                                    Ver planeación
                                </Link>
                            </Button>
                            {permissions.create_expense_dossier && usableLines.length > 0 && (
                                <Button type="button" variant="outline" onClick={openExpenseForm}>
                                    <FileText className="size-4" />
                                    Nuevo gasto
                                </Button>
                            )}
                            {permissions.manage && usableLines.length > 0 && (
                                <Button type="button" onClick={() => openForm()}>
                                    <Plus className="size-4" />
                                    Registrar modificación
                                </Button>
                            )}
                        </div>
                    </div>
                </header>

                <section className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3" aria-label="Resumen del presupuesto">
                    <SummaryCard label="Presupuesto inicial" value={summary.initial_amount_cents} />
                    <SummaryCard label="Presupuesto modificado" value={summary.modified_amount_cents} />
                    <SummaryCard label="Reservado" value={summary.reserved_amount_cents} />
                    <SummaryCard label="Comprometido" value={summary.committed_amount_cents} />
                    <SummaryCard label="Pagado" value={summary.paid_amount_cents} />
                    <SummaryCard label="Disponible" value={summary.available_amount_cents} accent />
                </section>

                <Card>
                    <CardHeader>
                        <CardTitle>Saldos por partida y mes</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[1180px] text-sm">
                                <thead className="border-y bg-muted/50 text-left text-xs text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">Partida</th>
                                        <th className="px-3 py-3 font-medium">Mes</th>
                                        <th className="px-3 py-3 text-right font-medium">Inicial</th>
                                        <th className="px-3 py-3 text-right font-medium">Entradas</th>
                                        <th className="px-3 py-3 text-right font-medium">Salidas</th>
                                        <th className="px-3 py-3 text-right font-medium">Modificado</th>
                                        <th className="px-3 py-3 text-right font-medium">Reservado</th>
                                        <th className="px-3 py-3 text-right font-medium">Comprometido</th>
                                        <th className="px-3 py-3 text-right font-medium">Pagado</th>
                                        <th className="px-3 py-3 text-right font-medium">Disponible</th>
                                        <th className="px-4 py-3"><span className="sr-only">Acciones</span></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {lines.map((line) => (
                                        <tr key={line.id}>
                                            <td className="px-4 py-3">
                                                <p className="font-medium">{line.specific_item_code}</p>
                                                <p className="max-w-sm text-xs text-muted-foreground">{line.specific_item_name}</p>
                                            </td>
                                            <td className="px-3 py-3">{months[line.month]}</td>
                                            <MoneyCell value={line.initial_amount_cents} />
                                            <MoneyCell value={line.incoming_amount_cents} />
                                            <MoneyCell value={line.outgoing_amount_cents} />
                                            <MoneyCell value={line.modified_amount_cents} strong />
                                            <MoneyCell value={line.reserved_amount_cents} />
                                            <MoneyCell value={line.committed_amount_cents} />
                                            <MoneyCell value={line.paid_amount_cents} />
                                            <MoneyCell value={line.available_amount_cents} strong />
                                            <td className="px-4 py-3 text-right">
                                                {permissions.manage && BigInt(line.available_amount_cents) > 0n && (
                                                    <Button type="button" variant="outline" size="sm" onClick={() => openForm(line)}>
                                                        Modificar
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex-row items-center justify-between gap-3">
                        <CardTitle>Expedientes de gasto</CardTitle>
                        {permissions.create_expense_dossier && usableLines.length > 0 && (
                            <Button type="button" size="sm" onClick={openExpenseForm}>
                                <Plus className="size-4" /> Nuevo gasto
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {expenseDossiers.map((dossier) => (
                            <article key={dossier.id} className="grid gap-3 rounded-lg border p-4 text-sm md:grid-cols-[1fr_auto]">
                                <div className="grid gap-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-semibold">{dossier.folio}</span>
                                        <Badge variant="outline">{dossierStatusLabels[dossier.status]}</Badge>
                                    </div>
                                    <p>{dossier.concept}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {dossier.line.specific_item_code} · {months[dossier.line.month]} · {dossier.requested_by_name}
                                    </p>
                                    {dossier.purchase_reference !== null && (
                                        <p className="text-xs text-muted-foreground">Compra: {dossier.purchase_reference}</p>
                                    )}
                                    {dossier.payment_request_reference !== null && (
                                        <p className="text-xs text-muted-foreground">Solicitud de pago: {dossier.payment_request_reference}</p>
                                    )}
                                    {dossier.finance_authorization_reference !== null && (
                                        <p className="text-xs text-muted-foreground">Finanzas: {dossier.finance_authorization_reference}</p>
                                    )}
                                    {dossier.budget_office_authorization_reference !== null && (
                                        <p className="text-xs text-muted-foreground">Presupuesto/Pagaduría: {dossier.budget_office_authorization_reference}</p>
                                    )}
                                    {dossier.payment_reference !== null && (
                                        <p className="text-xs text-muted-foreground">Pago: {dossier.payment_reference}</p>
                                    )}
                                    {dossier.documents.length > 0 && (
                                        <div className="flex flex-wrap gap-2 pt-1">
                                            {dossier.documents.map((document) => (
                                                <Button key={document.id} asChild variant="outline" size="sm">
                                                    <a href={expenseDossierDocuments.download(document.id).url}>
                                                        <Download className="size-3" /> {document.original_name}
                                                    </a>
                                                </Button>
                                            ))}
                                        </div>
                                    )}
                                </div>
                                <div className="flex flex-col items-end justify-between gap-2">
                                    <span className="font-semibold">{formatCents(dossier.amount_cents)}</span>
                                    {dossier.status === 'draft' && permissions.request_expense_sufficiency && (
                                        <Button type="button" size="sm" onClick={() => advanceDossier(dossier, 'request')}>
                                            Solicitar suficiencia
                                        </Button>
                                    )}
                                    {dossier.status === 'sufficiency_requested' && permissions.confirm_expense_sufficiency && (
                                        <Button type="button" size="sm" onClick={() => advanceDossier(dossier, 'confirm')}>
                                            Confirmar suficiencia
                                        </Button>
                                    )}
                                    {dossier.status === 'sufficiency_confirmed' && permissions.manage_expense_purchase && (
                                        <Button type="button" size="sm" onClick={() => openPurchaseForm(dossier)}>
                                            Iniciar compra
                                        </Button>
                                    )}
                                    {dossier.status === 'purchase_in_progress' && permissions.manage_expense_purchase && (
                                        <Button type="button" size="sm" onClick={() => openPaymentForm(dossier)}>
                                            Solicitar pago
                                        </Button>
                                    )}
                                    {dossier.status === 'payment_requested' && permissions.authorize_expense_payment && (
                                        <Button type="button" size="sm" onClick={() => openAuthorizationForm(dossier, 'finance')}>
                                            Autorizar en Finanzas
                                        </Button>
                                    )}
                                    {dossier.status === 'finance_authorized' && permissions.authorize_expense_payment && (
                                        <Button type="button" size="sm" onClick={() => openAuthorizationForm(dossier, 'budget-office')}>
                                            Autorizar en Presupuesto
                                        </Button>
                                    )}
                                    {dossier.status === 'budget_office_authorized' && permissions.authorize_expense_payment && (
                                        <Button type="button" size="sm" onClick={() => openAuthorizationForm(dossier, 'payment')}>
                                            Registrar pago
                                        </Button>
                                    )}
                                </div>
                            </article>
                        ))}
                        {expenseDossiers.length === 0 && (
                            <p className="text-sm text-muted-foreground">Aún no hay expedientes de gasto en este ejercicio.</p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Historial de modificaciones</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {history.map((movement) => (
                            <article key={movement.id} className="grid gap-2 rounded-lg border p-4 text-sm">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <Badge variant="outline">
                                        {movement.type === 'transfer' ? <ArrowRightLeft className="size-3" /> : <CalendarClock className="size-3" />}
                                        {movement.type === 'transfer' ? 'Transferencia' : 'Recalendarización'}
                                    </Badge>
                                    <span className="font-semibold">{formatCents(movement.amount_cents)}</span>
                                </div>
                                <p>
                                    {movement.source.specific_item_code} · {months[movement.source.month]}
                                    {' → '}
                                    {movement.destination.specific_item_code} · {months[movement.destination.month]}
                                </p>
                                <p className="text-muted-foreground">{movement.reason}</p>
                                <p className="text-xs text-muted-foreground">
                                    {movement.recorded_by_name} · {displayDate(movement.recorded_at)}
                                </p>
                            </article>
                        ))}
                        {history.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                Aún no se han registrado transferencias ni recalendarizaciones.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </main>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Registrar modificación presupuestal</DialogTitle>
                        <DialogDescription>
                            El sistema conservará el movimiento y los saldos anteriores para consulta.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="grid gap-4">
                        <Field label="Tipo de movimiento" error={form.errors.type}>
                            <select
                                value={form.data.type}
                                onChange={(event) => resetDestination(event.target.value as ModificationForm['type'], selectedSource)}
                                className={selectClassName}
                            >
                                <option value="transfer">Transferencia entre partidas</option>
                                <option value="rescheduling">Mover al presupuesto de un mes futuro</option>
                            </select>
                        </Field>
                        <Field label="Partida de origen" error={form.errors.source_line_id}>
                            <select
                                value={form.data.source_line_id}
                                onChange={(event) => {
                                    const source = lines.find((line) => line.id.toString() === event.target.value);
                                    resetDestination(form.data.type, source);
                                }}
                                className={selectClassName}
                            >
                                {usableLines.map((line) => (
                                    <option key={line.id} value={line.id}>
                                        {line.specific_item_code} · {months[line.month]} · {formatCents(line.available_amount_cents)} disponible
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Partida de destino" error={form.errors.destination_expense_classification_id}>
                            <select
                                value={form.data.destination_expense_classification_id}
                                onChange={(event) => form.setData('destination_expense_classification_id', event.target.value)}
                                className={selectClassName}
                                required
                            >
                                <option value="">Selecciona una partida</option>
                                {destinationOptions.map((classification) => (
                                    <option key={classification.id} value={classification.id}>
                                        {classification.specific_item_code} · {classification.specific_item_name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Mes de destino" error={form.errors.destination_month}>
                            <select
                                value={form.data.destination_month}
                                onChange={(event) => form.setData('destination_month', event.target.value)}
                                className={selectClassName}
                                disabled={form.data.type === 'transfer'}
                                required
                            >
                                <option value="">Selecciona un mes</option>
                                {months.slice(1).map((month, index) => {
                                    const number = index + 1;

                                    return number > (selectedSource?.month ?? 12) || form.data.type === 'transfer' ? (
                                        <option key={month} value={number}>{month}</option>
                                    ) : null;
                                })}
                            </select>
                        </Field>
                        <Field label="Importe a mover (pesos)" error={form.errors.amount_cents}>
                            <Input
                                value={form.data.amount_pesos}
                                onChange={(event) => form.setData('amount_pesos', event.target.value)}
                                inputMode="decimal"
                                placeholder="0.00"
                                required
                            />
                        </Field>
                        <Field label="Motivo" error={form.errors.reason}>
                            <textarea
                                value={form.data.reason}
                                onChange={(event) => form.setData('reason', event.target.value)}
                                className={`${selectClassName} min-h-24 py-2`}
                                maxLength={2000}
                                required
                            />
                        </Field>
                        <Button type="submit" disabled={form.processing || destinationOptions.length === 0}>
                            {form.processing ? 'Registrando…' : 'Registrar modificación'}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={expenseDialogOpen} onOpenChange={setExpenseDialogOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Nuevo expediente de gasto</DialogTitle>
                        <DialogDescription>Guárdalo como borrador y solicita la suficiencia cuando esté listo.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitExpense} className="grid gap-4">
                        <Field label="Partida y mes" error={expenseForm.errors.own_revenue_modified_budget_line_id}>
                            <select value={expenseForm.data.own_revenue_modified_budget_line_id} onChange={(event) => expenseForm.setData('own_revenue_modified_budget_line_id', event.target.value)} className={selectClassName} required>
                                {usableLines.map((line) => (
                                    <option key={line.id} value={line.id}>{line.specific_item_code} · {months[line.month]} · {formatCents(line.available_amount_cents)} disponible</option>
                                ))}
                            </select>
                        </Field>
                        <Field label="Concepto del gasto" error={expenseForm.errors.concept}>
                            <textarea value={expenseForm.data.concept} onChange={(event) => expenseForm.setData('concept', event.target.value)} className={`${selectClassName} min-h-24 py-2`} maxLength={2000} required />
                        </Field>
                        <Field label="Importe (pesos)" error={expenseForm.errors.amount_cents}>
                            <Input value={expenseForm.data.amount_pesos} onChange={(event) => expenseForm.setData('amount_pesos', event.target.value)} inputMode="decimal" placeholder="0.00" required />
                        </Field>
                        <Field label="Responsable de la compra" error={expenseForm.errors.purchase_responsibility}>
                            <select value={expenseForm.data.purchase_responsibility} onChange={(event) => expenseForm.setData('purchase_responsibility', event.target.value as 'cren' | 'seq')} className={selectClassName}>
                                <option value="cren">CREN</option>
                                <option value="seq">SEQ</option>
                            </select>
                        </Field>
                        <Field label="Referencia externa (opcional)" error={expenseForm.errors.external_reference}>
                            <Input value={expenseForm.data.external_reference} onChange={(event) => expenseForm.setData('external_reference', event.target.value)} maxLength={255} />
                        </Field>
                        <Field label="Observaciones (opcional)" error={expenseForm.errors.notes}>
                            <textarea value={expenseForm.data.notes} onChange={(event) => expenseForm.setData('notes', event.target.value)} className={`${selectClassName} min-h-20 py-2`} maxLength={4000} />
                        </Field>
                        <Button type="submit" disabled={expenseForm.processing}>{expenseForm.processing ? 'Guardando…' : 'Guardar borrador'}</Button>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={purchaseDossier !== null} onOpenChange={(open) => !open && setPurchaseDossier(null)}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Iniciar compra o contratación</DialogTitle>
                        <DialogDescription>
                            Registra la orden, contrato o referencia que identifica el trámite de {purchaseDossier?.folio}.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitPurchase} className="grid gap-4">
                        <Field label="Referencia de compra" error={purchaseForm.errors.purchase_reference}>
                            <Input
                                value={purchaseForm.data.purchase_reference}
                                onChange={(event) => purchaseForm.setData('purchase_reference', event.target.value)}
                                maxLength={255}
                                placeholder="Ej. OC-CREN-2026-001"
                                required
                            />
                        </Field>
                        <Button type="submit" disabled={purchaseForm.processing}>
                            {purchaseForm.processing ? 'Registrando…' : 'Iniciar compra'}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={paymentDossier !== null} onOpenChange={(open) => !open && setPaymentDossier(null)}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Solicitar pago</DialogTitle>
                        <DialogDescription>
                            Registra la referencia y adjunta al menos un PDF, XML, imagen o XLSX de {paymentDossier?.folio}.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitPayment} className="grid gap-4">
                        <Field label="Referencia de la solicitud" error={paymentForm.errors.payment_request_reference}>
                            <Input
                                value={paymentForm.data.payment_request_reference}
                                onChange={(event) => paymentForm.setData('payment_request_reference', event.target.value)}
                                maxLength={255}
                                placeholder="Ej. SP-CREN-2026-001"
                                required
                            />
                        </Field>
                        <Field label="Documentos" error={paymentForm.errors.documents}>
                            <Input
                                type="file"
                                multiple
                                accept=".pdf,.xml,.jpg,.jpeg,.png,.xlsx"
                                onChange={(event) => paymentForm.setData('documents', Array.from(event.target.files ?? []))}
                                required
                            />
                            <p className="text-xs text-muted-foreground">Hasta 10 archivos de 10 MB cada uno.</p>
                        </Field>
                        <Button type="submit" disabled={paymentForm.processing}>
                            {paymentForm.processing ? 'Enviando…' : 'Registrar solicitud de pago'}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={authorizationAction !== null} onOpenChange={(open) => !open && setAuthorizationAction(null)}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {authorizationAction === null ? 'Registrar autorización' : authorizationCopy[authorizationAction.kind].title}
                        </DialogTitle>
                        <DialogDescription>
                            {authorizationAction === null ? '' : `${authorizationCopy[authorizationAction.kind].description} Expediente ${authorizationAction.dossier.folio}.`}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitAuthorization} className="grid gap-4">
                        <Field
                            label={authorizationAction === null ? 'Referencia' : authorizationCopy[authorizationAction.kind].label}
                            error={authorizationForm.errors.reference}
                        >
                            <Input
                                value={authorizationForm.data.reference}
                                onChange={(event) => authorizationForm.setData('reference', event.target.value)}
                                maxLength={255}
                                required
                            />
                        </Field>
                        <Button type="submit" disabled={authorizationForm.processing}>
                            {authorizationForm.processing
                                ? 'Registrando…'
                                : authorizationAction === null
                                  ? 'Registrar'
                                  : authorizationCopy[authorizationAction.kind].button}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
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

function MoneyCell({ value, strong = false }: { value: string; strong?: boolean }) {
    return <td className={`px-3 py-3 text-right tabular-nums ${strong ? 'font-semibold' : ''}`}>{formatCents(value)}</td>;
}

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}
