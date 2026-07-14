import { Link, useForm, usePage } from '@inertiajs/react';
import { Check, LoaderCircle, X } from 'lucide-react';
import OwnRevenueImportDecisionController from '@/actions/App/Http/Controllers/Finance/OwnRevenueImportDecisionController';
import OwnRevenueSupportingConfirmationController from '@/actions/App/Http/Controllers/Finance/OwnRevenueSupportingConfirmationController';
import { supportingPreviewActions } from '@/components/finance/own-revenue/imports/import-workspace-state';
import {
    formatCents,
    previewPageQuery,
} from '@/components/finance/own-revenue/imports/work-sheet-preview-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { preview as showPreview } from '@/routes/finance/own-revenue/budgets/imports/files';
import type {
    OwnRevenueImportFormat,
    OwnRevenueImportIssue,
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

function WarningDecision({
    budgetId,
    fileId,
    analysisRevision,
    issue,
}: {
    budgetId: number;
    fileId: number;
    analysisRevision: string;
    issue: OwnRevenueImportIssue;
}) {
    const form = useForm({
        analysis_revision: analysisRevision,
        decision: issue.decision?.status ?? '',
        justification: issue.decision?.justification ?? '',
    });
    const submit = (decision: 'accepted' | 'rejected'): void => {
        form.transform((data) => ({ ...data, decision }));
        form.submit(
            OwnRevenueImportDecisionController({
                budget: budgetId,
                importFile: fileId,
                issue: issue.id,
            }),
            { preserveScroll: true },
        );
    };

    return (
        <div className="grid gap-3 rounded-md border bg-background p-3">
            <div>
                <p className="text-sm font-medium">{issue.message}</p>
                {issue.context.row_number && (
                    <p className="text-xs text-muted-foreground">
                        Renglón {issue.context.row_number}
                    </p>
                )}
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`supporting-justification-${issue.id}`}>
                    Nota de la decisión (opcional)
                </Label>
                <textarea
                    id={`supporting-justification-${issue.id}`}
                    value={form.data.justification}
                    onChange={(event) =>
                        form.setData('justification', event.target.value)
                    }
                    rows={2}
                    maxLength={2000}
                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
            </div>
            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    size="sm"
                    onClick={() => submit('accepted')}
                    disabled={form.processing}
                >
                    <Check className="size-4" />
                    Aceptar
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    onClick={() => submit('rejected')}
                    disabled={form.processing}
                >
                    <X className="size-4" />
                    No aceptar
                </Button>
            </div>
            <InputError
                message={form.errors.decision ?? form.errors.analysis_revision}
            />
        </div>
    );
}

export default function SupportingFormatPreview({
    budget,
    selected_file: file,
    preview,
    decision_warnings: decisionWarnings,
    can_confirm: canConfirm,
    confirm_reasons: confirmReasons,
    permissions,
}: OwnRevenueSupportingPreviewProps) {
    const currentUrl = usePage().url;
    const format = file.format as SupportingFormat;
    const confirmationForm = useForm({
        analysis_revision: file.analysis_revision ?? '',
        file: '',
    });
    const previewState = supportingPreviewActions({
        status: file.status,
        confirmed: file.confirmed,
        canManage: permissions.manage,
        canConfirm: permissions.confirm,
    });
    const route = (page: number) =>
        showPreview(
            { budget: budget.id, importFile: file.id },
            { query: previewPageQuery(currentUrl, 'preview_page', page) },
        );
    const confirmFile = (): void => {
        if (
            !canConfirm ||
            !window.confirm(
                'Confirmar este archivo guardará su detalle y reemplazará la versión confirmada anterior. La actividad se asignará durante la conciliación. ¿Deseas continuar?',
            )
        ) {
            return;
        }

        confirmationForm.submit(
            OwnRevenueSupportingConfirmationController({
                budget: budget.id,
                importFile: file.id,
            }),
            { preserveScroll: true },
        );
    };

    return (
        <div className="grid gap-4">
            <Card>
                <CardHeader>
                    <CardTitle>Información encontrada</CardTitle>
                    <CardDescription>
                        {preview.total} renglones disponibles para revisión.{' '}
                        {previewState.isClosed
                            ? 'El detalle quedó conservado como evidencia de esta importación.'
                            : 'Al confirmar se conservará este detalle; la actividad se asignará durante la conciliación con la Hoja de trabajo.'}
                    </CardDescription>
                </CardHeader>
                {canConfirm && (
                    <CardContent className="grid gap-2">
                        <Button
                            type="button"
                            className="w-fit"
                            onClick={confirmFile}
                            disabled={confirmationForm.processing}
                        >
                            {confirmationForm.processing ? (
                                <LoaderCircle className="size-4 animate-spin" />
                            ) : (
                                <Check className="size-4" />
                            )}
                            Confirmar archivo
                        </Button>
                        <InputError
                            message={
                                confirmationForm.errors.file ??
                                confirmationForm.errors.analysis_revision
                            }
                        />
                    </CardContent>
                )}
            </Card>

            {decisionWarnings.total > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Advertencias por revisar</CardTitle>
                        <CardDescription>
                            {previewState.isClosed
                                ? 'Las decisiones registradas se conservan como parte de la revisión.'
                                : 'Registra una decisión para cada advertencia antes de confirmar el archivo.'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {decisionWarnings.data.map((issue) =>
                            previewState.decisionsEnabled ? (
                                <WarningDecision
                                    key={issue.id}
                                    budgetId={budget.id}
                                    fileId={file.id}
                                    analysisRevision={
                                        file.analysis_revision ?? ''
                                    }
                                    issue={issue}
                                />
                            ) : (
                                <div
                                    key={issue.id}
                                    className="rounded-md border bg-background p-3 text-sm"
                                >
                                    {issue.message}
                                </div>
                            ),
                        )}
                    </CardContent>
                </Card>
            )}

            {!canConfirm &&
                previewState.showConfirmReasons &&
                confirmReasons.length > 0 && (
                    <Card>
                        <CardContent className="pt-6">
                            <p className="text-sm font-medium">
                                Antes de confirmar:
                            </p>
                            <ul className="mt-2 list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                                {confirmReasons.map((reason) => (
                                    <li key={reason}>{reason}</li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

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
