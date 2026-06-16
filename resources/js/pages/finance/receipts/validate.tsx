import { Head } from '@inertiajs/react';

type Receipt = {
    folio: string;
    type: string;
    status: string;
    total_pesos: number;
    amount_in_words: string;
    issued_at: string | null;
    student: {
        name: string;
        grade: string | null;
        group: string | null;
    };
};

type Props = {
    receipt: Receipt;
};

export default function ReceiptValidate({ receipt }: Props) {
    return (
        <>
            <Head title={`Validación ${receipt.folio}`} />
            <main className="mx-auto flex min-h-screen w-full max-w-3xl flex-col gap-4 p-4 sm:p-8">
                <header>
                    <p className="text-sm text-muted-foreground">Validación de recibo CREN</p>
                    <h1 className="text-2xl font-semibold">{receipt.folio}</h1>
                </header>

                <section className="rounded-lg border p-4">
                    <dl className="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt className="text-muted-foreground">Estado</dt>
                            <dd className="font-medium">{receipt.status}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Tipo</dt>
                            <dd className="font-medium">{receipt.type}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Estudiante</dt>
                            <dd className="font-medium">{receipt.student.name}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Grado y grupo</dt>
                            <dd className="font-medium">
                                {receipt.student.grade}
                                {receipt.student.group}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Total</dt>
                            <dd className="font-medium">${receipt.total_pesos.toLocaleString('es-MX')}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Emitido</dt>
                            <dd className="font-medium">
                                {receipt.issued_at ? new Date(receipt.issued_at).toLocaleString() : 'Pendiente'}
                            </dd>
                        </div>
                        <div className="sm:col-span-2">
                            <dt className="text-muted-foreground">Cantidad en letras</dt>
                            <dd className="font-medium">{receipt.amount_in_words}</dd>
                        </div>
                    </dl>
                </section>
            </main>
        </>
    );
}
