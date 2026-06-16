import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Copy,
    ExternalLink,
    Printer,
    ReceiptText,
    ShieldCheck,
    TriangleAlert,
    UserRound,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import {
    money,
    ReceiptStatusBadge,
    ReceiptTypeBadge,
} from '@/components/finance/finance-badges';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';

type Receipt = {
    id: number;
    folio: string;
    type: string;
    status: string;
    total_pesos: number;
    amount_in_words: string;
    issued_at: string | null;
    validation_url: string;
    qr_svg: string;
    can_cancel: boolean;
    procedure_id: number;
    procedure_folio: string | null;
    transaction_folio: string;
    student: {
        name: string;
        grade: string | null;
        group: string | null;
        matricula: string | null;
        program: string | null;
    };
    item: {
        concept_name: string;
        official_fee_code: string | null;
        official_fee_name: string | null;
        subtotal_pesos: number;
    } | null;
    cancellation: {
        reason: string;
        cancelled_at: string | null;
        cancelled_by: string;
    } | null;
};

type Props = {
    receipt: Receipt;
};

export default function ReceiptShow({ receipt }: Props) {
    const [reason, setReason] = useState('');
    const [copiedText, copy] = useClipboard();
    const procedureLabel = receipt.procedure_folio ?? `#${receipt.procedure_id}`;
    const issuedAt = formatDateTime(receipt.issued_at);
    const copiedValidationUrl = copiedText === receipt.validation_url;

    function cancelReceipt(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        router.post(
            `/finance/receipts/${receipt.id}/cancel`,
            { reason },
            {
                preserveScroll: true,
            },
        );
    }

    return (
        <>
            <Head title={`Recibo ${receipt.folio}`} />
            <main className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <header className="rounded-lg border bg-muted/20 p-4">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div className="grid gap-3">
                            <div className="flex flex-wrap items-center gap-2">
                                <ReceiptText className="size-5 text-muted-foreground" />
                                <h1 className="text-xl font-semibold">
                                    Recibo {receipt.folio}
                                </h1>
                                <ReceiptTypeBadge type={receipt.type} />
                                <ReceiptStatusBadge status={receipt.status} />
                            </div>
                            <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                <UserRound className="size-4" />
                                <span>{receipt.student.name}</span>
                                <span>
                                    {[
                                        receipt.student.matricula,
                                        receipt.student.grade && receipt.student.group
                                            ? `${receipt.student.grade}${receipt.student.group}`
                                            : receipt.student.grade,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </span>
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <Button asChild variant="outline" size="sm">
                                <Link
                                    href={`/finance/payment-procedures/${receipt.procedure_id}`}
                                    aria-label={`Volver al trámite ${procedureLabel}`}
                                >
                                    <ArrowLeft className="size-4" />
                                    Trámite {procedureLabel}
                                </Link>
                            </Button>
                            <Button asChild variant="outline" size="sm">
                                <Link
                                    href={`/finance/receipts/${receipt.id}/print`}
                                    aria-label={`Imprimir recibo ${receipt.folio}`}
                                >
                                    <Printer className="size-4" />
                                    Imprimir
                                </Link>
                            </Button>
                        </div>
                    </div>

                    <dl className="mt-4 grid gap-3 border-t pt-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <dt className="text-muted-foreground">Total</dt>
                            <dd className="text-lg font-semibold tabular-nums">
                                {money(receipt.total_pesos)}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Emitido</dt>
                            <dd className="font-medium">{issuedAt}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Transacción</dt>
                            <dd className="font-medium tabular-nums">
                                {receipt.transaction_folio}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Trámite</dt>
                            <dd className="font-medium tabular-nums">
                                {procedureLabel}
                            </dd>
                        </div>
                    </dl>
                </header>

                <section className="grid gap-4 md:grid-cols-[1fr_18rem]">
                    <div className="rounded-lg border">
                        <div className="flex items-center gap-2 border-b bg-muted/60 px-3 py-2">
                            <ReceiptText className="size-4 text-muted-foreground" />
                            <h2 className="text-sm font-semibold">
                                Detalle del recibo
                            </h2>
                        </div>
                        <dl className="grid gap-3 p-4 text-sm sm:grid-cols-2">
                            <div>
                                <dt className="text-muted-foreground">Estudiante</dt>
                                <dd className="font-medium">{receipt.student.name}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Matrícula</dt>
                                <dd className="font-medium">
                                    {receipt.student.matricula ?? 'Sin matrícula'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Programa</dt>
                                <dd className="font-medium">
                                    {receipt.student.program ?? 'Sin programa'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Grupo</dt>
                                <dd className="font-medium">
                                    {[receipt.student.grade, receipt.student.group]
                                        .filter(Boolean)
                                        .join(' ') || 'Sin grupo'}
                                </dd>
                            </div>
                            <div className="sm:col-span-2">
                                <dt className="text-muted-foreground">Cantidad en letras</dt>
                                <dd className="font-medium">{receipt.amount_in_words}</dd>
                            </div>
                            {receipt.item ? (
                                <div className="sm:col-span-2">
                                    <dt className="text-muted-foreground">Concepto externo</dt>
                                    <dd className="font-medium">{receipt.item.concept_name}</dd>
                                </div>
                            ) : null}
                            {receipt.item?.official_fee_code ||
                            receipt.item?.official_fee_name ? (
                                <div className="sm:col-span-2">
                                    <dt className="text-muted-foreground">
                                        Concepto oficial
                                    </dt>
                                    <dd className="font-medium">
                                        {[
                                            receipt.item.official_fee_code,
                                            receipt.item.official_fee_name,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </dd>
                                </div>
                            ) : null}
                        </dl>
                    </div>

                    <aside className="rounded-lg border p-4">
                        <div className="flex items-center gap-2">
                            <ShieldCheck className="size-4 text-muted-foreground" />
                            <p className="text-sm font-medium">Validación</p>
                        </div>
                        <div
                            className="mt-3 grid aspect-square place-items-center rounded-md border bg-white p-3"
                            dangerouslySetInnerHTML={{ __html: receipt.qr_svg }}
                        />
                        <p className="mt-3 break-all rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground">
                            {receipt.validation_url}
                        </p>
                        <div className="mt-3 grid gap-2">
                            <Button asChild variant="outline" size="sm">
                                <a href={receipt.validation_url}>
                                    <ExternalLink className="size-4" />
                                    Abrir validación
                                </a>
                            </Button>
                            <Button
                                variant="secondary"
                                size="sm"
                                type="button"
                                onClick={() => void copy(receipt.validation_url)}
                            >
                                <Copy className="size-4" />
                                {copiedValidationUrl ? 'Enlace copiado' : 'Copiar enlace'}
                            </Button>
                        </div>
                    </aside>
                </section>

                {receipt.cancellation ? (
                    <section className="rounded-lg border p-4">
                        <h2 className="text-sm font-semibold">Cancelación</h2>
                        <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                            <div>
                                <dt className="text-muted-foreground">Responsable</dt>
                                <dd className="font-medium">{receipt.cancellation.cancelled_by}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Fecha</dt>
                                <dd className="font-medium">
                                    {receipt.cancellation.cancelled_at
                                        ? formatDateTime(receipt.cancellation.cancelled_at)
                                        : ''}
                                </dd>
                            </div>
                            <div className="sm:col-span-3">
                                <dt className="text-muted-foreground">Motivo</dt>
                                <dd className="font-medium">{receipt.cancellation.reason}</dd>
                            </div>
                        </dl>
                    </section>
                ) : null}

                {receipt.can_cancel ? (
                    <form
                        className="rounded-lg border border-destructive/30 bg-destructive/5 p-4"
                        onSubmit={cancelReceipt}
                    >
                        <div className="mb-3 flex items-start gap-2">
                            <TriangleAlert className="mt-0.5 size-4 text-destructive" />
                            <div>
                                <h2 className="text-sm font-semibold">
                                    Cancelar recibo
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Esta acción deja el recibo sin validez operativa y
                                    conserva el motivo para auditoría.
                                </p>
                            </div>
                        </div>
                        <label className="grid gap-2 text-sm">
                            <span className="font-medium">Motivo de cancelación</span>
                            <textarea
                                className="min-h-24 rounded-md border bg-background p-2"
                                minLength={10}
                                maxLength={1000}
                                placeholder="Describe el motivo de la cancelación"
                                required
                                value={reason}
                                onChange={(event) => setReason(event.target.value)}
                            />
                        </label>
                        <Button className="mt-3" variant="destructive" type="submit">
                            <TriangleAlert className="size-4" />
                            Cancelar
                        </Button>
                    </form>
                ) : null}
            </main>
        </>
    );
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return 'Pendiente';
    }

    return new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: 'America/Cancun',
    }).format(new Date(value));
}
