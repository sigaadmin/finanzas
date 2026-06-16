import { Head } from '@inertiajs/react';

type Receipt = {
    folio: string;
    type: string;
    total_pesos: number;
    amount_in_words: string;
    issued_at: string | null;
    validation_url: string;
    qr_svg: string;
    student: {
        name: string;
        grade: string | null;
        group: string | null;
    };
    item: {
        concept_name: string;
        subtotal_pesos: number;
    };
};

type Props = {
    receipt: Receipt;
};

function SeqBlock({ receipt, label }: Props & { label: string }) {
    return (
        <section className="border border-neutral-950 p-4">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-xs font-bold">{label}</p>
                    <h1 className="text-lg font-bold">RECIBO INGRESOS PROPIOS</h1>
                    <p className="text-xs">Centro Regional de Educación Normal Felipe Carrillo Puerto</p>
                    <p className="text-xs">Clave 23DNL0002N</p>
                </div>
                <div className="text-right">
                    <p className="text-xs font-semibold">Folio</p>
                    <p className="text-lg font-bold">{receipt.folio}</p>
                </div>
            </div>

            <div className="mt-4 grid gap-4 text-sm sm:grid-cols-[1fr_8rem]">
                <dl className="grid gap-2">
                    <div>
                        <dt className="font-semibold">Recibí de</dt>
                        <dd>{receipt.student.name}</dd>
                    </div>
                    <div>
                        <dt className="font-semibold">Grupo</dt>
                        <dd>
                            {receipt.student.grade}
                            {receipt.student.group}
                        </dd>
                    </div>
                    <div>
                        <dt className="font-semibold">La cantidad de</dt>
                        <dd>{receipt.amount_in_words}</dd>
                    </div>
                    <div>
                        <dt className="font-semibold">Por concepto de</dt>
                        <dd>{receipt.item.concept_name}</dd>
                    </div>
                </dl>
                <div className="grid place-items-start" dangerouslySetInnerHTML={{ __html: receipt.qr_svg }} />
            </div>

            <div className="mt-6 grid grid-cols-2 gap-6 text-center text-xs">
                <div className="border-t border-neutral-950 pt-2">RECIBÍ PAGO</div>
                <div className="border-t border-neutral-950 pt-2">SELLO</div>
            </div>

            <p className="mt-3 text-right text-sm font-bold">${receipt.total_pesos.toLocaleString('es-MX')}</p>
        </section>
    );
}

export default function PrintExternalSeqReceipt({ receipt }: Props) {
    return (
        <>
            <Head title={`SEQ ${receipt.folio}`} />
            <main className="mx-auto grid min-h-screen max-w-4xl gap-6 bg-white p-8 text-neutral-950 print:p-4">
                <SeqBlock receipt={receipt} label="ORIGINAL" />
                <SeqBlock receipt={receipt} label="COPIA" />
            </main>
        </>
    );
}
