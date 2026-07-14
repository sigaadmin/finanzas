import { Link, usePage } from '@inertiajs/react';
import {
    formatCents,
    previewPageQuery,
} from '@/components/finance/own-revenue/imports/work-sheet-preview-state';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { preview as showPreview } from '@/routes/finance/own-revenue/budgets/imports/files';
import type {
    OwnRevenueImportFormat,
    OwnRevenueSupportingPreviewProps,
} from '@/types/finance-own-revenue-imports';

type SupportingFormat = Exclude<OwnRevenueImportFormat, 'abpre' | 'work_sheet'>;

type Field = readonly [key: string, label: string, kind?: 'money' | 'month'];

const fields: Record<SupportingFormat, Field[]> = {
    technical_sheet: [
        ['specificItemCode', 'Partida'],
        ['quantity', 'Cantidad'],
        ['unit', 'Unidad'],
        ['description', 'Descripción'],
        ['regionCode', 'Región'],
        ['amountCents', 'Costo', 'money'],
        ['budgetMonth', 'Mes presupuestado', 'month'],
    ],
    fuel: [
        ['month', 'Mes', 'month'],
        ['reason', 'Motivo de la comisión'],
        ['vehicleModel', 'Vehículo'],
        ['kilometersPerLiter', 'Rendimiento'],
        ['outboundOrigin', 'Origen'],
        ['outboundDestination', 'Destino'],
        ['outboundKilometers', 'Kilómetros de ida'],
        ['returnKilometers', 'Kilómetros de regreso'],
        ['liters', 'Litros previstos'],
        ['fuelPrice', 'Precio por litro'],
        ['amountCents', 'Importe', 'money'],
    ],
    travel_expenses: [
        ['month', 'Mes', 'month'],
        ['reason', 'Motivo de la comisión'],
        ['personName', 'Personal comisionado'],
        ['position', 'Cargo'],
        ['commissionDays', 'Días de comisión'],
        ['destination', 'Destino'],
        ['perDiemUma', 'UMA para viáticos'],
        ['lodgingUma', 'UMA para hospedaje'],
        ['umaValue', 'Valor UMA'],
        ['perDiemAmountCents', 'Viáticos', 'money'],
        ['lodgingAmountCents', 'Hospedaje', 'money'],
        ['flightAmountCents', 'Transportación aérea', 'money'],
        ['totalAmountCents', 'Total sin transportación aérea', 'money'],
    ],
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

function presentedValue(
    value: string | number | null | undefined,
    kind?: 'money' | 'month',
): string {
    if (value === null || value === undefined || value === '') {
        return 'Sin dato';
    }

    if (kind === 'money') {
        return formatCents(String(value));
    }

    if (kind === 'month') {
        return months[Number(value)] ?? String(value);
    }

    return String(value);
}

export default function SupportingFormatPreview({
    budget,
    selected_file: file,
    preview,
}: OwnRevenueSupportingPreviewProps) {
    const currentUrl = usePage().url;
    const format = file.format as SupportingFormat;
    const route = (page: number) =>
        showPreview(
            { budget: budget.id, importFile: file.id },
            { query: previewPageQuery(currentUrl, 'preview_page', page) },
        );

    return (
        <div className="grid gap-4">
            <Card>
                <CardHeader>
                    <CardTitle>Información encontrada</CardTitle>
                    <CardDescription>
                        {preview.total} renglones disponibles para revisión. La
                        confirmación se habilitará cuando exista el módulo de
                        planeación correspondiente.
                    </CardDescription>
                </CardHeader>
            </Card>

            {preview.data.map((row) => (
                <Card key={row.id}>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base">
                            Renglón {row.row_number}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2 xl:grid-cols-3">
                            {fields[format].map(([key, label, kind]) => (
                                <div key={key} className="min-w-0">
                                    <dt className="text-xs font-medium text-muted-foreground">
                                        {label}
                                    </dt>
                                    <dd className="mt-1 text-sm break-words">
                                        {presentedValue(row.values[key], kind)}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </CardContent>
                </Card>
            ))}

            {preview.last_page > 1 && (
                <nav
                    className="flex items-center justify-between gap-2"
                    aria-label="Páginas de la vista previa"
                >
                    <Button
                        asChild={preview.current_page > 1}
                        size="sm"
                        variant="outline"
                        disabled={preview.current_page <= 1}
                    >
                        {preview.current_page > 1 ? (
                            <Link
                                href={route(preview.current_page - 1)}
                                preserveScroll
                                preserveState
                            >
                                Anterior
                            </Link>
                        ) : (
                            <span>Anterior</span>
                        )}
                    </Button>
                    <span className="text-xs text-muted-foreground">
                        Página {preview.current_page} de {preview.last_page}
                    </span>
                    <Button
                        asChild={Boolean(preview.next_page_url)}
                        size="sm"
                        variant="outline"
                        disabled={!preview.next_page_url}
                    >
                        {preview.next_page_url ? (
                            <Link
                                href={route(preview.current_page + 1)}
                                preserveScroll
                                preserveState
                            >
                                Siguiente
                            </Link>
                        ) : (
                            <span>Siguiente</span>
                        )}
                    </Button>
                </nav>
            )}
        </div>
    );
}
