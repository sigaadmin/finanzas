import { Link, router, useForm, usePage } from '@inertiajs/react';
import {
    AlertCircle,
    CheckCircle2,
    Download,
    FileSpreadsheet,
    LoaderCircle,
    Search,
    Trash2,
    Upload,
} from 'lucide-react';
import { useRef, useState } from 'react';
import OwnRevenueImportAnalysisController from '@/actions/App/Http/Controllers/Finance/OwnRevenueImportAnalysisController';
import OwnRevenueImportFileController from '@/actions/App/Http/Controllers/Finance/OwnRevenueImportFileController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { show } from '@/routes/finance/own-revenue/budgets/imports';
import type {
    OwnRevenueImportFile,
    OwnRevenueImportFormat,
    OwnRevenueImportPermissions,
    OwnRevenueImportSlot,
} from '@/types/finance-own-revenue-imports';

type Props = {
    budgetId: number;
    slot: OwnRevenueImportSlot;
    selectedFileId: number | null;
    permissions: OwnRevenueImportPermissions;
};

type UploadData = {
    file: File | null;
    force_reanalysis: boolean;
};

const statusLabels: Record<OwnRevenueImportFile['status'], string> = {
    uploaded: 'Cargado',
    analyzing: 'Analizando',
    needs_correction: 'Requiere corrección',
    ready: 'Listo',
    confirmed: 'Confirmado',
    replaced: 'Reemplazado',
    discarded: 'Descartado',
    failed: 'Falló',
    parser_pending: 'Parser pendiente',
};

function queryWith(
    currentUrl: string,
    name: string,
    value: string | number,
): Record<string, string> {
    const query = new URLSearchParams(currentUrl.split('?')[1] ?? '');
    query.set(name, String(value));

    return Object.fromEntries(query.entries());
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function canDiscard(file: OwnRevenueImportFile): boolean {
    return (
        !file.confirmed &&
        !['confirmed', 'replaced', 'discarded'].includes(file.status)
    );
}

export default function ImportFileSlot({
    budgetId,
    slot,
    selectedFileId,
    permissions,
}: Props) {
    const currentUrl = usePage().url;
    const inputRef = useRef<HTMLInputElement>(null);
    const [isDragging, setIsDragging] = useState(false);
    const uploadForm = useForm<UploadData>({
        file: null,
        force_reanalysis: false,
    });

    const submitFile = (file: File, forceReanalysis = false): void => {
        if (!file.name.toLowerCase().endsWith('.xlsx')) {
            uploadForm.setError(
                'file',
                'Selecciona un archivo con extensión .xlsx.',
            );

            return;
        }

        if (
            forceReanalysis &&
            !window.confirm(
                'Este archivo podría estar duplicado. ¿Deseas cargar una nueva versión y volver a analizarlo?',
            )
        ) {
            return;
        }

        uploadForm.setData({ file, force_reanalysis: forceReanalysis });
        uploadForm.clearErrors();
        uploadForm.transform(() => ({
            file,
            force_reanalysis: forceReanalysis,
        }));
        uploadForm.submit(OwnRevenueImportFileController.store(budgetId), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => uploadForm.reset(),
        });
    };

    const assignFormat = (
        file: OwnRevenueImportFile,
        format: OwnRevenueImportFormat,
    ): void => {
        router.put(
            OwnRevenueImportFileController.updateFormat({
                budget: budgetId,
                importFile: file.id,
            }),
            { format },
            { preserveScroll: true },
        );
    };

    return (
        <Card className="min-w-0">
            <CardHeader className="gap-2">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <FileSpreadsheet className="size-4" />
                            {slot.label}
                        </CardTitle>
                        <CardDescription>
                            {slot.versions_total === 0
                                ? 'Sin versiones cargadas'
                                : `${slot.versions_total} ${slot.versions_total === 1 ? 'versión' : 'versiones'}`}
                        </CardDescription>
                    </div>
                    {slot.versions.some((file) => file.confirmed) ? (
                        <Badge className="bg-emerald-600">Confirmado</Badge>
                    ) : slot.versions.some(
                          (file) => file.status === 'parser_pending',
                      ) ? (
                        <Badge variant="outline">Parser pendiente</Badge>
                    ) : (
                        <Badge variant="secondary">Pendiente</Badge>
                    )}
                </div>
            </CardHeader>
            <CardContent className="grid gap-4">
                {permissions.upload && (
                    <div className="grid gap-2">
                        <input
                            ref={inputRef}
                            type="file"
                            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            className="sr-only"
                            aria-label={`Seleccionar XLSX para ${slot.label}`}
                            onChange={(event) => {
                                const file = event.target.files?.[0];

                                if (file) {
                                    submitFile(file);
                                }

                                event.target.value = '';
                            }}
                        />
                        <button
                            type="button"
                            onClick={() => inputRef.current?.click()}
                            onDragEnter={(event) => {
                                event.preventDefault();
                                setIsDragging(true);
                            }}
                            onDragOver={(event) => event.preventDefault()}
                            onDragLeave={() => setIsDragging(false)}
                            onDrop={(event) => {
                                event.preventDefault();
                                setIsDragging(false);
                                const file = event.dataTransfer.files[0];

                                if (file) {
                                    submitFile(file);
                                }
                            }}
                            disabled={uploadForm.processing}
                            className={`grid min-h-28 place-items-center rounded-lg border border-dashed p-4 text-center transition-colors focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none ${isDragging ? 'border-primary bg-primary/5' : 'border-border hover:bg-muted/50'} disabled:cursor-not-allowed disabled:opacity-60`}
                        >
                            <span className="grid justify-items-center gap-1 text-sm">
                                {uploadForm.processing ? (
                                    <LoaderCircle className="size-5 animate-spin" />
                                ) : (
                                    <Upload className="size-5" />
                                )}
                                <span className="font-medium">
                                    Selecciona o arrastra un XLSX
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    Máximo 20 MB. El archivo se procesa en el
                                    servidor.
                                </span>
                            </span>
                        </button>
                        {uploadForm.data.file && uploadForm.processing && (
                            <p className="truncate text-xs text-muted-foreground">
                                {uploadForm.data.file.name} ·{' '}
                                {formatBytes(uploadForm.data.file.size)}
                            </p>
                        )}
                        {uploadForm.progress && (
                            <div className="grid gap-1" aria-live="polite">
                                <div className="flex justify-between text-xs text-muted-foreground">
                                    <span>Cargando</span>
                                    <span>
                                        {uploadForm.progress.percentage}%
                                    </span>
                                </div>
                                <progress
                                    className="h-2 w-full accent-primary"
                                    value={uploadForm.progress.percentage}
                                    max={100}
                                />
                            </div>
                        )}
                        <InputError message={uploadForm.errors.file} />
                        {uploadForm.errors.file?.includes('ya fue cargado') && (
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => {
                                    if (uploadForm.data.file) {
                                        submitFile(uploadForm.data.file, true);
                                    }
                                }}
                            >
                                Forzar nuevo análisis
                            </Button>
                        )}
                    </div>
                )}

                <div className="grid gap-2">
                    {slot.versions.length === 0 ? (
                        <p className="rounded-lg bg-muted/50 p-3 text-sm text-muted-foreground">
                            Este formato todavía no tiene historial.
                        </p>
                    ) : (
                        slot.versions.map((file) => (
                            <article
                                key={file.id}
                                className={`grid gap-2 rounded-lg border p-3 ${selectedFileId === file.id ? 'border-primary bg-primary/5' : ''}`}
                            >
                                <div className="flex min-w-0 items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <Link
                                            href={show(budgetId, {
                                                query: queryWith(
                                                    currentUrl,
                                                    'import_file_id',
                                                    file.id,
                                                ),
                                            })}
                                            preserveScroll
                                            className="block truncate text-sm font-medium hover:underline"
                                        >
                                            {file.name}
                                        </Link>
                                        <p className="text-xs text-muted-foreground">
                                            v{file.version} ·{' '}
                                            {formatBytes(file.size)}
                                            {file.year ? ` · ${file.year}` : ''}
                                        </p>
                                    </div>
                                    <Badge variant="outline">
                                        {statusLabels[file.status]}
                                    </Badge>
                                </div>
                                <div className="flex flex-wrap items-center gap-2 text-xs">
                                    {file.status === 'analyzing' && (
                                        <span className="flex items-center gap-1 text-blue-700 dark:text-blue-300">
                                            <LoaderCircle className="size-3 animate-spin" />{' '}
                                            Procesando
                                        </span>
                                    )}
                                    {file.issue_counts.error > 0 && (
                                        <span className="flex items-center gap-1 text-destructive">
                                            <AlertCircle className="size-3" />{' '}
                                            {file.issue_counts.error} errores
                                        </span>
                                    )}
                                    {file.status === 'ready' && (
                                        <span className="flex items-center gap-1 text-emerald-700 dark:text-emerald-300">
                                            <CheckCircle2 className="size-3" />{' '}
                                            Listo para confirmar
                                        </span>
                                    )}
                                </div>
                                <div className="flex flex-wrap gap-1">
                                    {permissions.download && (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="ghost"
                                        >
                                            <a
                                                href={
                                                    OwnRevenueImportFileController.download(
                                                        {
                                                            budget: budgetId,
                                                            importFile: file.id,
                                                        },
                                                    ).url
                                                }
                                            >
                                                <Download className="size-3" />{' '}
                                                Descargar
                                            </a>
                                        </Button>
                                    )}
                                    {permissions.manage &&
                                        file.format === 'abpre' &&
                                        [
                                            'uploaded',
                                            'needs_correction',
                                            'failed',
                                        ].includes(file.status) && (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    router.post(
                                                        OwnRevenueImportAnalysisController(
                                                            {
                                                                budget: budgetId,
                                                                importFile:
                                                                    file.id,
                                                            },
                                                        ),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                <Search className="size-3" />{' '}
                                                Analizar ABPRE
                                            </Button>
                                        )}
                                    {permissions.manage && !file.confirmed && (
                                        <select
                                            aria-label={`Corregir tipo de ${file.name}`}
                                            value={file.format ?? ''}
                                            onChange={(event) =>
                                                assignFormat(
                                                    file,
                                                    event.target
                                                        .value as OwnRevenueImportFormat,
                                                )
                                            }
                                            className="h-8 rounded-md border border-input bg-background px-2 text-xs"
                                        >
                                            <option value="" disabled>
                                                Corregir tipo
                                            </option>
                                            <option value="abpre">ABPRE</option>
                                            <option value="work_sheet">
                                                Hoja de trabajo
                                            </option>
                                            <option value="technical_sheet">
                                                Ficha técnica
                                            </option>
                                            <option value="fuel">
                                                Combustible
                                            </option>
                                            <option value="travel_expenses">
                                                Viáticos
                                            </option>
                                        </select>
                                    )}
                                    {permissions.manage && canDiscard(file) && (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => {
                                                if (
                                                    window.confirm(
                                                        `Descartar la versión ${file.version} de ${file.name}?`,
                                                    )
                                                ) {
                                                    router.delete(
                                                        OwnRevenueImportFileController.destroy(
                                                            {
                                                                budget: budgetId,
                                                                importFile:
                                                                    file.id,
                                                            },
                                                        ),
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    );
                                                }
                                            }}
                                        >
                                            <Trash2 className="size-3" />{' '}
                                            Descartar
                                        </Button>
                                    )}
                                </div>
                            </article>
                        ))
                    )}
                </div>

                {slot.versions_last_page > 1 && (
                    <nav
                        className="flex items-center justify-between gap-2"
                        aria-label={`Versiones de ${slot.label}`}
                    >
                        <Button
                            asChild
                            size="sm"
                            variant="outline"
                            disabled={slot.versions_current_page === 1}
                        >
                            <Link
                                href={show(budgetId, {
                                    query: queryWith(
                                        currentUrl,
                                        `${slot.format}_versions_page`,
                                        Math.max(
                                            1,
                                            slot.versions_current_page - 1,
                                        ),
                                    ),
                                })}
                                preserveScroll
                            >
                                Anterior
                            </Link>
                        </Button>
                        <span className="text-xs text-muted-foreground">
                            Página {slot.versions_current_page} de{' '}
                            {slot.versions_last_page}
                        </span>
                        <Button
                            asChild
                            size="sm"
                            variant="outline"
                            disabled={!slot.versions_has_more}
                        >
                            <Link
                                href={show(budgetId, {
                                    query: queryWith(
                                        currentUrl,
                                        `${slot.format}_versions_page`,
                                        slot.versions_current_page + 1,
                                    ),
                                })}
                                preserveScroll
                            >
                                Siguiente
                            </Link>
                        </Button>
                    </nav>
                )}
            </CardContent>
        </Card>
    );
}
