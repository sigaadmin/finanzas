import { Head, Link, useForm } from '@inertiajs/react';
import { Download, LoaderCircle, Save } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import finance from '@/routes/finance';

type Sheet = {
    item_name: string | null;
    objective: string | null;
    work_description: string | null;
    technical_specs: string | null;
    beneficiaries: string | null;
    scheduled_date: string | null;
    deliverables: string | null;
    delivery_location: string | null;
    supervisor: string | null;
    payment_terms: string | null;
};

type BudgetLine = {
    id: number;
    action_number: string;
    action_name: string;
    action_justification: string | null;
    cog_code: string | null;
    cog_name: string | null;
    amount_cents: number;
    exercise_month: string | null;
    description: string | null;
    sheet: Sheet | null;
};

type Props = {
    program: {
        id: number;
        fiscal_year: number;
        name: string;
        shared_sheet_fields: {
            delivery_location: string | null;
            supervisor: string | null;
            payment_terms: string | null;
        };
        lines: BudgetLine[];
    };
};

type SheetFormData = {
    shared_sheet_fields: {
        delivery_location: string;
        supervisor: string;
        payment_terms: string;
    };
    sheets: Array<
        Sheet & {
            u300_budget_line_id: number;
        }
    >;
};

function money(cents: number): string {
    return (cents / 100).toLocaleString('es-MX', {
        style: 'currency',
        currency: 'MXN',
    });
}

function TextAreaField({
    label,
    value,
    onChange,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <textarea
                className="min-h-20 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
        </div>
    );
}

export default function U300TechnicalSheets({ program }: Props) {
    const form = useForm<SheetFormData>({
        shared_sheet_fields: {
            delivery_location:
                program.shared_sheet_fields.delivery_location ?? '',
            supervisor: program.shared_sheet_fields.supervisor ?? '',
            payment_terms: program.shared_sheet_fields.payment_terms ?? '',
        },
        sheets: program.lines.map((line) => ({
            u300_budget_line_id: line.id,
            item_name: line.sheet?.item_name ?? line.description ?? '',
            objective: line.sheet?.objective ?? line.action_justification ?? '',
            work_description: line.sheet?.work_description ?? '',
            technical_specs: line.sheet?.technical_specs ?? '',
            beneficiaries: line.sheet?.beneficiaries ?? '',
            scheduled_date: line.sheet?.scheduled_date ?? line.exercise_month,
            deliverables: line.sheet?.deliverables ?? '',
            delivery_location: line.sheet?.delivery_location ?? '',
            supervisor: line.sheet?.supervisor ?? '',
            payment_terms: line.sheet?.payment_terms ?? '',
        })),
    });

    function sheetFor(lineId: number) {
        return form.data.sheets.find(
            (sheet) => sheet.u300_budget_line_id === lineId,
        );
    }

    function updateSheet(
        lineId: number,
        updates: Partial<SheetFormData['sheets'][number]>,
    ): void {
        form.setData(
            'sheets',
            form.data.sheets.map((sheet) =>
                sheet.u300_budget_line_id === lineId
                    ? { ...sheet, ...updates }
                    : sheet,
            ),
        );
    }

    function updateSharedSheetField(
        field: keyof SheetFormData['shared_sheet_fields'],
        value: string,
    ): void {
        form.setData('shared_sheet_fields', {
            ...form.data.shared_sheet_fields,
            [field]: value,
        });
    }

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.transform((data) => ({
            sheets: data.sheets.map((sheet) => ({
                ...sheet,
                delivery_location: data.shared_sheet_fields.delivery_location,
                supervisor: data.shared_sheet_fields.supervisor,
                payment_terms: data.shared_sheet_fields.payment_terms,
            })),
        }));
        form.put(finance.u300.programs.technicalSheets.update(program).url);
    }

    return (
        <>
            <Head title="Fichas técnicas U300" />
            <main className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <header className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Presupuesto U300 · {program.fiscal_year}
                        </p>
                        <h1 className="text-xl font-semibold">
                            Fichas técnicas
                        </h1>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild variant="outline">
                            <Link href={finance.u300.programs.show(program)}>
                                Volver
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <a
                                href={
                                    finance.u300.programs.technicalSheets.export(
                                        program,
                                    ).url
                                }
                            >
                                <Download className="size-4" />
                                Exportar Word
                            </a>
                        </Button>
                        <Button
                            disabled={form.processing}
                            form="u300-technical-sheets-form"
                            type="submit"
                        >
                            {form.processing ? (
                                <LoaderCircle className="size-4 animate-spin" />
                            ) : (
                                <Save className="size-4" />
                            )}
                            Guardar
                        </Button>
                    </div>
                </header>

                <form id="u300-technical-sheets-form" onSubmit={submit}>
                    <div className="grid gap-4">
                        <section className="grid gap-4 rounded-lg border p-4">
                            <div className="grid gap-1">
                                <h2 className="text-sm font-semibold">
                                    Datos comunes de entrega
                                </h2>
                            </div>
                            <div className="grid gap-4 lg:grid-cols-2">
                                <TextAreaField
                                    label="Lugar de entrega"
                                    value={
                                        form.data.shared_sheet_fields
                                            .delivery_location
                                    }
                                    onChange={(value) =>
                                        updateSharedSheetField(
                                            'delivery_location',
                                            value,
                                        )
                                    }
                                />
                                <TextAreaField
                                    label="Responsable de supervisión"
                                    value={
                                        form.data.shared_sheet_fields.supervisor
                                    }
                                    onChange={(value) =>
                                        updateSharedSheetField(
                                            'supervisor',
                                            value,
                                        )
                                    }
                                />
                                <div className="lg:col-span-2">
                                    <TextAreaField
                                        label="Condiciones y forma de pago"
                                        value={
                                            form.data.shared_sheet_fields
                                                .payment_terms
                                        }
                                        onChange={(value) =>
                                            updateSharedSheetField(
                                                'payment_terms',
                                                value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                        </section>

                        {program.lines.map((line) => {
                            const sheet = sheetFor(line.id);

                            return (
                                <section
                                    key={line.id}
                                    id={`ficha-tecnica-${line.id}`}
                                    className="grid gap-4 rounded-lg border p-4"
                                >
                                    <div className="grid gap-1">
                                        <h2 className="text-sm font-semibold">
                                            {line.action_number}{' '}
                                            {line.action_name}
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            {line.cog_code} · {line.cog_name} ·{' '}
                                            {money(line.amount_cents)}
                                        </p>
                                    </div>

                                    <div className="grid gap-4 lg:grid-cols-2">
                                        <div className="lg:col-span-2">
                                            <TextAreaField
                                                label="Nombre del bien o servicio"
                                                value={sheet?.item_name ?? ''}
                                                onChange={(value) =>
                                                    updateSheet(line.id, {
                                                        item_name: value,
                                                    })
                                                }
                                            />
                                        </div>
                                        <TextAreaField
                                            label="Objetivo"
                                            value={sheet?.objective ?? ''}
                                            onChange={(value) =>
                                                updateSheet(line.id, {
                                                    objective: value,
                                                })
                                            }
                                        />
                                        <TextAreaField
                                            label="Trabajos a realizar"
                                            value={
                                                sheet?.work_description ?? ''
                                            }
                                            onChange={(value) =>
                                                updateSheet(line.id, {
                                                    work_description: value,
                                                })
                                            }
                                        />
                                        <TextAreaField
                                            label="Perfil / especificaciones técnicas"
                                            value={sheet?.technical_specs ?? ''}
                                            onChange={(value) =>
                                                updateSheet(line.id, {
                                                    technical_specs: value,
                                                })
                                            }
                                        />
                                        <TextAreaField
                                            label="Entregables"
                                            value={sheet?.deliverables ?? ''}
                                            onChange={(value) =>
                                                updateSheet(line.id, {
                                                    deliverables: value,
                                                })
                                            }
                                        />
                                        <div className="grid gap-2 lg:col-span-2">
                                            <Label>Beneficiarios</Label>
                                            <Input
                                                value={
                                                    sheet?.beneficiaries ?? ''
                                                }
                                                onChange={(event) =>
                                                    updateSheet(line.id, {
                                                        beneficiaries:
                                                            event.target.value,
                                                    })
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label>Fecha</Label>
                                            <Input
                                                value={
                                                    sheet?.scheduled_date ?? ''
                                                }
                                                onChange={(event) =>
                                                    updateSheet(line.id, {
                                                        scheduled_date:
                                                            event.target.value,
                                                    })
                                                }
                                            />
                                        </div>
                                    </div>
                                </section>
                            );
                        })}
                    </div>
                    <InputError message={form.errors.sheets} />
                </form>
            </main>
        </>
    );
}
