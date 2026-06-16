import { Head, Link } from '@inertiajs/react';
import {
    ClipboardList,
    FilePlus2,
    FileText,
    ReceiptText,
    TableProperties,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import finance from '@/routes/finance';

type Props = {
    metrics?: {
        pending_procedures: number;
        paid_today: number;
        receipts_issued_today: number;
        external_receipts_this_month: number;
    };
};

export default function Dashboard({ metrics }: Props) {
    const actions = [
        {
            title: 'Nuevo trámite',
            description: 'Iniciar cobro para estudiante',
            href: finance.paymentProcedures.create(),
            icon: FilePlus2,
        },
        {
            title: 'Trámites',
            description: 'Consultar pagos y recibos',
            href: finance.paymentProcedures.index(),
            icon: ClipboardList,
        },
        {
            title: 'Conceptos',
            description: 'Montos y servicios de cobro',
            href: finance.chargeConcepts.index(),
            icon: FileText,
        },
        {
            title: 'Reporte SEQ',
            description: 'Revisar y exportar ingresos',
            href: finance.seqReport.index(),
            icon: TableProperties,
        },
    ];

    const summary = [
        {
            title: 'Pendientes',
            value: metrics?.pending_procedures ?? 0,
            tone: 'border-amber-200 bg-amber-50/60 dark:border-amber-900 dark:bg-amber-950/20',
        },
        {
            title: 'Pagados hoy',
            value: metrics?.paid_today ?? 0,
            tone: 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-900 dark:bg-emerald-950/20',
        },
        {
            title: 'Recibos hoy',
            value: metrics?.receipts_issued_today ?? 0,
            tone: 'border-sky-200 bg-sky-50/60 dark:border-sky-900 dark:bg-sky-950/20',
        },
        {
            title: 'Externos del mes',
            value: metrics?.external_receipts_this_month ?? 0,
            tone: 'border-indigo-200 bg-indigo-50/60 dark:border-indigo-900 dark:bg-indigo-950/20',
        },
    ];

    return (
        <>
            <Head title="Portal Financiero" />
            <main className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <header className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            CREN Felipe Carrillo Puerto
                        </p>
                        <h1 className="text-xl font-semibold">
                            Portal Financiero
                        </h1>
                    </div>
                    <Button asChild>
                        <Link href={finance.paymentProcedures.create()}>
                            <FilePlus2 className="size-4" />
                            Nuevo trámite
                        </Link>
                    </Button>
                </header>

                <section className="grid gap-3 md:grid-cols-4">
                    {summary.map((metric) => (
                        <div
                            key={metric.title}
                            className={`rounded-lg border p-4 ${metric.tone}`}
                        >
                            <p className="text-sm text-muted-foreground">{metric.title}</p>
                            <p className="mt-2 text-2xl font-semibold">{metric.value}</p>
                        </div>
                    ))}
                </section>

                <section className="grid gap-3 md:grid-cols-4">
                    {actions.map((action) => (
                        <Link
                            key={action.title}
                            href={action.href}
                            className="rounded-lg border p-4 transition hover:bg-muted/60"
                        >
                            <action.icon className="size-5 text-primary" />
                            <h2 className="mt-3 text-sm font-semibold">{action.title}</h2>
                            <p className="mt-1 text-sm text-muted-foreground">{action.description}</p>
                        </Link>
                    ))}
                </section>

                <section className="grid gap-4 lg:grid-cols-[1fr_20rem]">
                    <div className="rounded-lg border">
                        <div className="flex items-center gap-2 border-b bg-muted/60 px-4 py-3">
                            <ReceiptText className="size-4 text-muted-foreground" />
                            <h2 className="text-sm font-semibold">Flujo de caja</h2>
                        </div>
                        <ol className="grid gap-0 text-sm">
                            {[
                                'Seleccionar estudiante desde SIGA2',
                                'Agregar uno o varios conceptos de cobro',
                                'Registrar pago y folio de transacción',
                                'Imprimir el recibo del CREN y los recibos SEQ necesarios',
                            ].map((step, index) => (
                                <li key={step} className="flex gap-3 border-b px-4 py-3 last:border-b-0">
                                    <span className="grid size-6 shrink-0 place-items-center rounded-full border text-xs font-medium">
                                        {index + 1}
                                    </span>
                                    <span>{step}</span>
                                </li>
                            ))}
                        </ol>
                    </div>

                    <div className="rounded-lg border border-indigo-200 bg-indigo-50/50 p-4 dark:border-indigo-900 dark:bg-indigo-950/20">
                        <div className="flex items-center gap-2">
                            <TableProperties className="size-4 text-indigo-700 dark:text-indigo-300" />
                            <h2 className="text-sm font-semibold">
                                Corte externo SEQ
                            </h2>
                        </div>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Revisa recibos externos, depósitos asociados y
                            exportación mensual.
                        </p>
                        <Button asChild className="mt-4" variant="outline">
                            <Link href={finance.seqReport.index()}>
                                Ver reporte
                            </Link>
                        </Button>
                    </div>
                </section>
            </main>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Inicio',
            href: dashboard(),
        },
    ],
};
