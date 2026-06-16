import { Badge } from '@/components/ui/badge';

const procedureStatus: Record<
    string,
    { label: string; className: string }
> = {
    pending_payment: {
        label: 'Pendiente de pago',
        className: 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200',
    },
    paid: {
        label: 'Pagado',
        className: 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200',
    },
    cancelled: {
        label: 'Cancelado',
        className: 'border-red-300 bg-red-50 text-red-800 dark:border-red-700 dark:bg-red-950/40 dark:text-red-200',
    },
};

const receiptStatus: Record<string, { label: string; className: string }> = {
    issued: {
        label: 'Emitido',
        className: 'border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-700 dark:bg-sky-950/40 dark:text-sky-200',
    },
    reprinted: {
        label: 'Reimpreso',
        className: 'border-indigo-300 bg-indigo-50 text-indigo-800 dark:border-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-200',
    },
    cancelled: {
        label: 'Cancelado',
        className: 'border-red-300 bg-red-50 text-red-800 dark:border-red-700 dark:bg-red-950/40 dark:text-red-200',
    },
};

const receiptType: Record<string, { label: string; className: string }> = {
    internal: {
        label: 'Interno',
        className: 'border-slate-300 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200',
    },
    external: {
        label: 'SEQ',
        className: 'border-indigo-300 bg-indigo-50 text-indigo-800 dark:border-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-200',
    },
};

export function money(amountPesos: number): string {
    return `$${amountPesos.toLocaleString('es-MX')}`;
}

export function ProcedureStatusBadge({ status }: { status: string }) {
    const option = procedureStatus[status] ?? {
        label: status,
        className: 'border-border bg-muted text-muted-foreground',
    };

    return (
        <Badge variant="outline" className={option.className}>
            {option.label}
        </Badge>
    );
}

export function ReceiptStatusBadge({ status }: { status: string }) {
    const option = receiptStatus[status] ?? {
        label: status,
        className: 'border-border bg-muted text-muted-foreground',
    };

    return (
        <Badge variant="outline" className={option.className}>
            {option.label}
        </Badge>
    );
}

export function ReceiptTypeBadge({ type }: { type: string }) {
    const option = receiptType[type] ?? {
        label: type,
        className: 'border-border bg-muted text-muted-foreground',
    };

    return (
        <Badge variant="outline" className={option.className}>
            {option.label}
        </Badge>
    );
}

export function SeqDepositStatusBadge({
    registered,
}: {
    registered: boolean;
}) {
    return (
        <Badge
            variant="outline"
            className={
                registered
                    ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200'
                    : 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200'
            }
        >
            {registered ? 'Depósito registrado' : 'Pendiente de depósito'}
        </Badge>
    );
}
