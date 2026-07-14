import { Head, Link, useForm } from '@inertiajs/react';
import {
    Download,
    LoaderCircle,
    Package,
    Pencil,
    Save,
    Truck,
} from 'lucide-react';
import { useState } from 'react';
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
import { Label } from '@/components/ui/label';
import finance from '@/routes/finance';

type Sheet = {
    item_name: string | null;
    objective: string | null;
    work_description: string | null;
    technical_specs: string | null;
    has_goods_profile: boolean;
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
    default_scheduled_date: string | null;
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
        Omit<Sheet, 'has_goods_profile'> & {
            u300_budget_line_id: number;
        }
    >;
};

type ActionGroup = {
    key: string;
    action_number: string;
    action_name: string;
    lines: BudgetLine[];
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
    rows = 3,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    rows?: number;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <textarea
                className="min-h-20 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                rows={rows}
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
        </div>
    );
}

export default function U300TechnicalSheets({ program }: Props) {
    const [deliveryDialogOpen, setDeliveryDialogOpen] = useState(false);
    const [openActionKey, setOpenActionKey] = useState<string | null>(null);

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
            scheduled_date:
                line.sheet?.scheduled_date ?? line.default_scheduled_date ?? '',
            deliverables: line.sheet?.deliverables ?? '',
            delivery_location: line.sheet?.delivery_location ?? '',
            supervisor: line.sheet?.supervisor ?? '',
            payment_terms: line.sheet?.payment_terms ?? '',
        })),
    });

    const actionGroups = program.lines.reduce<ActionGroup[]>((groups, line) => {
        const key = `${line.action_number}|${line.action_name}`;
        const group = groups.find((currentGroup) => currentGroup.key === key);

        if (group) {
            group.lines.push(line);

            return groups;
        }

        groups.push({
            key,
            action_number: line.action_number,
            action_name: line.action_name,
            lines: [line],
        });

        return groups;
    }, []);

    function sheetFor(lineId: number) {
        return form.data.sheets.find(
            (sheet) => sheet.u300_budget_line_id === lineId,
        );
    }

    function updateActionSheets(
        action: ActionGroup,
        updates: Partial<Pick<Sheet, 'item_name' | 'objective'>>,
    ): void {
        const lineIds = new Set(action.lines.map((line) => line.id));

        form.setData(
            'sheets',
            form.data.sheets.map((sheet) =>
                lineIds.has(sheet.u300_budget_line_id)
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

    function lineCaptureUrl(lineId: number): string {
        return finance.u300.programs.technicalSheets.lines.edit({
            program,
            line: lineId,
        }).url;
    }

    function completedDetailCount(action: ActionGroup): number {
        return action.lines.filter((line) => {
            const sheet = sheetFor(line.id);

            return Boolean(
                sheet?.work_description ||
                sheet?.technical_specs ||
                line.sheet?.has_goods_profile ||
                sheet?.deliverables ||
                sheet?.beneficiaries,
            );
        }).length;
    }

    function sheetPayload(
        data: SheetFormData,
        stayOnPage: boolean,
    ): {
        stay_on_page?: boolean;
        sheets: SheetFormData['sheets'];
    } {
        return {
            ...(stayOnPage ? { stay_on_page: true } : {}),
            sheets: data.sheets.map((sheet) => ({
                ...sheet,
                delivery_location: data.shared_sheet_fields.delivery_location,
                supervisor: data.shared_sheet_fields.supervisor,
                payment_terms: data.shared_sheet_fields.payment_terms,
            })),
        };
    }

    function saveSheets({
        stayOnPage = false,
        onSuccess,
    }: {
        stayOnPage?: boolean;
        onSuccess?: () => void;
    } = {}): void {
        form.transform((data) => sheetPayload(data, stayOnPage));
        form.put(finance.u300.programs.technicalSheets.update(program).url, {
            preserveScroll: stayOnPage,
            onSuccess,
        });
    }

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        saveSheets();
    }

    function saveDeliveryFields(): void {
        saveSheets({
            stayOnPage: true,
            onSuccess: () => setDeliveryDialogOpen(false),
        });
    }

    function saveActionFields(): void {
        saveSheets({
            stayOnPage: true,
            onSuccess: () => setOpenActionKey(null),
        });
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
                    <div className="flex flex-wrap gap-2">
                        <Dialog
                            open={deliveryDialogOpen}
                            onOpenChange={setDeliveryDialogOpen}
                        >
                            <DialogTrigger asChild>
                                <Button type="button" variant="outline">
                                    <Truck className="size-4" />
                                    Datos de entrega
                                </Button>
                            </DialogTrigger>
                            <DialogContent className="sm:max-w-2xl">
                                <DialogHeader>
                                    <DialogTitle>
                                        Datos comunes de entrega
                                    </DialogTitle>
                                    <DialogDescription>
                                        Estos datos se aplican a todas las
                                        fichas al guardar.
                                    </DialogDescription>
                                </DialogHeader>
                                <div className="grid gap-4">
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
                                            form.data.shared_sheet_fields
                                                .supervisor
                                        }
                                        onChange={(value) =>
                                            updateSharedSheetField(
                                                'supervisor',
                                                value,
                                            )
                                        }
                                    />
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
                                <DialogFooter>
                                    <Button
                                        disabled={form.processing}
                                        type="button"
                                        onClick={saveDeliveryFields}
                                    >
                                        {form.processing && (
                                            <LoaderCircle className="size-4 animate-spin" />
                                        )}
                                        Listo
                                    </Button>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
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
                        <section className="grid gap-4">
                            {actionGroups.map((action) => {
                                const firstSheet = sheetFor(
                                    action.lines[0]?.id ?? 0,
                                );

                                return (
                                    <div
                                        key={action.key}
                                        className="grid gap-3 rounded-lg border p-4"
                                    >
                                        <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                            <div className="grid gap-1">
                                                <h2 className="text-sm font-semibold">
                                                    {action.action_number}{' '}
                                                    {action.action_name}
                                                </h2>
                                                <p className="text-sm text-muted-foreground">
                                                    {action.lines.length}{' '}
                                                    partidas ·{' '}
                                                    {completedDetailCount(
                                                        action,
                                                    )}
                                                    /{action.lines.length} con
                                                    detalle capturado
                                                </p>
                                            </div>
                                            <Dialog
                                                open={
                                                    openActionKey === action.key
                                                }
                                                onOpenChange={(open) =>
                                                    setOpenActionKey(
                                                        open
                                                            ? action.key
                                                            : null,
                                                    )
                                                }
                                            >
                                                <DialogTrigger asChild>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                    >
                                                        <Package className="size-4" />
                                                        Datos de la acción
                                                    </Button>
                                                </DialogTrigger>
                                                <DialogContent className="sm:max-w-2xl">
                                                    <DialogHeader>
                                                        <DialogTitle>
                                                            Datos generales de
                                                            la acción
                                                        </DialogTitle>
                                                        <DialogDescription>
                                                            El nombre del bien o
                                                            servicio y el
                                                            objetivo se aplican
                                                            a las partidas de
                                                            esta acción.
                                                        </DialogDescription>
                                                    </DialogHeader>
                                                    <div className="grid gap-4">
                                                        <TextAreaField
                                                            label="Nombre del bien o servicio"
                                                            value={
                                                                firstSheet?.item_name ??
                                                                ''
                                                            }
                                                            onChange={(value) =>
                                                                updateActionSheets(
                                                                    action,
                                                                    {
                                                                        item_name:
                                                                            value,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                        <TextAreaField
                                                            label="Objetivo"
                                                            value={
                                                                firstSheet?.objective ??
                                                                ''
                                                            }
                                                            onChange={(value) =>
                                                                updateActionSheets(
                                                                    action,
                                                                    {
                                                                        objective:
                                                                            value,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    </div>
                                                    <DialogFooter>
                                                        <Button
                                                            disabled={
                                                                form.processing
                                                            }
                                                            type="button"
                                                            onClick={
                                                                saveActionFields
                                                            }
                                                        >
                                                            {form.processing && (
                                                                <LoaderCircle className="size-4 animate-spin" />
                                                            )}
                                                            Listo
                                                        </Button>
                                                    </DialogFooter>
                                                </DialogContent>
                                            </Dialog>
                                        </div>

                                        <div className="overflow-hidden rounded-md border">
                                            <div className="grid grid-cols-[1fr_auto] gap-3 border-b bg-muted/40 px-3 py-2 text-xs font-medium text-muted-foreground md:grid-cols-[1.2fr_0.9fr_0.7fr_auto]">
                                                <span>Partida</span>
                                                <span className="hidden md:block">
                                                    Mes
                                                </span>
                                                <span className="hidden md:block">
                                                    Monto
                                                </span>
                                                <span>Captura</span>
                                            </div>
                                            {action.lines.map((line) => {
                                                return (
                                                    <div
                                                        key={line.id}
                                                        id={`ficha-tecnica-${line.id}`}
                                                        className="grid grid-cols-[1fr_auto] gap-3 border-b px-3 py-3 last:border-b-0 md:grid-cols-[1.2fr_0.9fr_0.7fr_auto] md:items-center"
                                                    >
                                                        <div className="grid gap-1">
                                                            <p className="text-sm font-medium">
                                                                {line.cog_code}{' '}
                                                                ·{' '}
                                                                {line.cog_name}
                                                            </p>
                                                        </div>
                                                        <p className="hidden text-sm text-muted-foreground md:block">
                                                            {line.default_scheduled_date ??
                                                                line.exercise_month ??
                                                                'Sin fecha'}
                                                        </p>
                                                        <p className="hidden text-sm font-medium md:block">
                                                            {money(
                                                                line.amount_cents,
                                                            )}
                                                        </p>
                                                        <Button
                                                            asChild
                                                            type="button"
                                                            variant="outline"
                                                        >
                                                            <Link
                                                                href={lineCaptureUrl(
                                                                    line.id,
                                                                )}
                                                            >
                                                                <Pencil className="size-4" />
                                                                Capturar
                                                            </Link>
                                                        </Button>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                );
                            })}
                        </section>
                    </div>
                    <InputError message={form.errors.sheets} />
                </form>
            </main>
        </>
    );
}
