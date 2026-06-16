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
        program: string | null;
        matricula: string | null;
    };
    items: Array<{
        id: number;
        concept_name: string;
        concept_type: string;
        quantity: number;
        unit_amount_pesos: number;
        subtotal_pesos: number;
    }>;
};

type Props = {
    receipt: Receipt;
};

export default function PrintInternalReceipt({ receipt }: Props) {
    return (
        <>
            <Head title={`Impresión ${receipt.folio}`} />
            <main className="mx-auto min-h-screen max-w-4xl bg-white p-8 text-neutral-950 print:p-4">
                <header className="border-b-4 border-[#40b2e4] pb-4">
                    <p className="text-sm font-semibold">Centro Regional de Educación Normal</p>
                    <h1 className="text-2xl font-bold">Recibo interno de pago</h1>
                    <p className="text-sm">Folio {receipt.folio}</p>
                </header>

                <section className="mt-6 grid gap-4 text-sm sm:grid-cols-[1fr_10rem]">
                    <dl className="grid gap-2 sm:grid-cols-2">
                        <div>
                            <dt className="font-semibold">Nombre</dt>
                            <dd>{receipt.student.name}</dd>
                        </div>
                        <div>
                            <dt className="font-semibold">Grado y grupo</dt>
                            <dd>
                                {receipt.student.grade}
                                {receipt.student.group}
                            </dd>
                        </div>
                        <div>
                            <dt className="font-semibold">Matrícula</dt>
                            <dd>{receipt.student.matricula}</dd>
                        </div>
                        <div>
                            <dt className="font-semibold">Fecha</dt>
                            <dd>{receipt.issued_at ? new Date(receipt.issued_at).toLocaleString() : ''}</dd>
                        </div>
                    </dl>
                    <div className="grid place-items-center" dangerouslySetInnerHTML={{ __html: receipt.qr_svg }} />
                </section>

                <table className="mt-6 w-full border-collapse text-sm">
                    <thead>
                        <tr className="bg-neutral-100 text-left">
                            <th className="border p-2">Concepto</th>
                            <th className="border p-2">Tipo</th>
                            <th className="border p-2 text-right">Cantidad</th>
                            <th className="border p-2 text-right">Importe</th>
                            <th className="border p-2 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        {receipt.items.map((item) => (
                            <tr key={item.id}>
                                <td className="border p-2">{item.concept_name}</td>
                                <td className="border p-2">{item.concept_type}</td>
                                <td className="border p-2 text-right">{item.quantity}</td>
                                <td className="border p-2 text-right">${item.unit_amount_pesos.toLocaleString('es-MX')}</td>
                                <td className="border p-2 text-right">${item.subtotal_pesos.toLocaleString('es-MX')}</td>
                            </tr>
                        ))}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td className="border p-2 text-right font-bold" colSpan={4}>
                                Total
                            </td>
                            <td className="border p-2 text-right font-bold">${receipt.total_pesos.toLocaleString('es-MX')}</td>
                        </tr>
                    </tfoot>
                </table>

                <p className="mt-4 text-sm font-semibold">{receipt.amount_in_words}</p>
                <p className="mt-2 text-xs">{receipt.validation_url}</p>
            </main>
        </>
    );
}
