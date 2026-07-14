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
import ImportIssueList from '@/components/finance/own-revenue/imports/import-issue-list';
import {
    failImportMutation,
    finishImportMutation,
    importFilePresentation,
    initialImportMutation,
    resolveFailedUpload,
    selectImportFileQuery,
    startImportMutation,
    takeNextUpload,
} from '@/components/finance/own-revenue/imports/import-workspace-state';
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
import { preview } from '@/routes/finance/own-revenue/budgets/imports/files';
import type {
    OwnRevenueImportFile,
    OwnRevenueImportFormat,
    OwnRevenueImportPermissions,
    OwnRevenueSelectedImportFile,
    OwnRevenueImportSlot,
} from '@/types/finance-own-revenue-imports';

type Props = {
    budgetId: number;
    slot: OwnRevenueImportSlot;
    selectedFile: OwnRevenueSelectedImportFile | null;
    permissions: OwnRevenueImportPermissions;
};

type UploadData = {
    file: File | null;
    force_reanalysis: boolean;
};

type UploadQueueEntry = {
    file: File;
    forceReanalysis: boolean;
};

type FailedUpload = {
    file: File;
    message: string;
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
    selectedFile,
    permissions,
}: Props) {
    const currentUrl = usePage().url;
    const inputRef = useRef<HTMLInputElement>(null);
    const [isDragging, setIsDragging] = useState(false);
    const [uploadQueue, setUploadQueue] = useState<UploadQueueEntry[]>([]);
    const uploadQueueRef = useRef<UploadQueueEntry[]>([]);
    const isUploadingRef = useRef(false);
    const [currentUpload, setCurrentUpload] = useState<UploadQueueEntry | null>(
        null,
    );
    const [failedUploads, setFailedUploads] = useState<FailedUpload[]>([]);
    const [duplicateUploads, setDuplicateUploads] = useState<File[]>([]);
    const [mutationFeedback, setMutationFeedback] = useState(
        initialImportMutation,
    );
    const uploadForm = useForm<UploadData>({
        file: null,
        force_reanalysis: false,
    });

    const replaceUploadQueue = (queue: UploadQueueEntry[]): void => {
        uploadQueueRef.current = queue;
        setUploadQueue(queue);
    };

    function startNextUpload(): void {
        if (isUploadingRef.current || uploadQueueRef.current.length === 0) {
            return;
        }

        const { current: nextUpload, remaining: remainingQueue } =
            takeNextUpload(uploadQueueRef.current);

        if (!nextUpload) {
            return;
        }

        isUploadingRef.current = true;
        replaceUploadQueue(remainingQueue);
        setCurrentUpload(nextUpload);
        uploadForm.clearErrors();
        uploadForm.setData({
            file: nextUpload.file,
            force_reanalysis: nextUpload.forceReanalysis,
        });
        uploadForm.transform(() => ({
            file: nextUpload.file,
            force_reanalysis: nextUpload.forceReanalysis,
        }));
        uploadForm.submit(OwnRevenueImportFileController.store(budgetId), {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => {
                const message =
                    errors.file ?? 'No fue posible cargar el archivo.';
                setFailedUploads((failures) => [
                    ...resolveFailedUpload(failures, nextUpload.file),
                    { file: nextUpload.file, message },
                ]);

                if (message.includes('ya fue cargado')) {
                    setDuplicateUploads((files) => [...files, nextUpload.file]);
                }
            },
            onSuccess: () => {
                setFailedUploads((failures) =>
                    resolveFailedUpload(failures, nextUpload.file),
                );
            },
            onFinish: () => {
                uploadForm.reset();
                isUploadingRef.current = false;
                setCurrentUpload(null);
                startNextUpload();
            },
        });
    }

    const enqueueFiles = (filesToQueue: File[]): void => {
        const accepted: UploadQueueEntry[] = [];
        const rejected: FailedUpload[] = [];

        filesToQueue.forEach((file) => {
            if (file.name.toLowerCase().endsWith('.xlsx')) {
                accepted.push({ file, forceReanalysis: false });
            } else {
                rejected.push({
                    file,
                    message: 'El archivo no tiene extensión .xlsx.',
                });
            }
        });

        setFailedUploads((failures) => [...failures, ...rejected]);
        replaceUploadQueue([...uploadQueueRef.current, ...accepted]);
        startNextUpload();
    };

    const retryDuplicate = (duplicateUpload: File): void => {
        if (
            !window.confirm(
                'Este archivo ya fue cargado. ¿Deseas crear una nueva versión y volver a analizarlo?',
            )
        ) {
            return;
        }

        replaceUploadQueue([
            { file: duplicateUpload, forceReanalysis: true },
            ...uploadQueueRef.current,
        ]);
        setDuplicateUploads((files) =>
            files.filter((file) => file !== duplicateUpload),
        );
        setFailedUploads((failures) =>
            resolveFailedUpload(failures, duplicateUpload),
        );
        startNextUpload();
    };

    const mutationOptions = () => ({
        preserveScroll: true,
        onError: (errors: Record<string, string>) =>
            setMutationFeedback((current) =>
                failImportMutation(current, errors),
            ),
        onFinish: () =>
            setMutationFeedback((current) => finishImportMutation(current)),
    });

    const assignFormat = (
        file: OwnRevenueImportFile,
        format: OwnRevenueImportFormat,
    ): void => {
        setMutationFeedback((current) => startImportMutation(current, file.id));
        router.put(
            OwnRevenueImportFileController.updateFormat({
                budget: budgetId,
                importFile: file.id,
            }),
            { format },
            mutationOptions(),
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
                    {slot.has_confirmed ? (
                        <Badge className="bg-emerald-600">Confirmado</Badge>
                    ) : slot.has_parser_pending ? (
                        <Badge variant="outline">
                            Revisión automática aún no disponible
                        </Badge>
                    ) : slot.is_missing && slot.versions_total > 0 ? (
                        <Badge variant="secondary">Sólo auditoría</Badge>
                    ) : (
                        <Badge variant="secondary">Pendiente</Badge>
                    )}
                </div>
            </CardHeader>
            <CardContent className="grid gap-4">
                {mutationFeedback.error && (
                    <p
                        role="alert"
                        aria-live="assertive"
                        className="rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive"
                    >
                        {mutationFeedback.error}
                    </p>
                )}
                {permissions.upload && (
                    <div className="grid gap-2">
                        <input
                            ref={inputRef}
                            type="file"
                            multiple
                            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            className="sr-only"
                            aria-label={`Seleccionar XLSX para ${slot.label}`}
                            onChange={(event) => {
                                if (event.target.files) {
                                    enqueueFiles(
                                        Array.from(event.target.files),
                                    );
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
                                enqueueFiles(
                                    Array.from(event.dataTransfer.files),
                                );
                            }}
                            disabled={currentUpload !== null}
                            className={`grid min-h-28 place-items-center rounded-lg border border-dashed p-4 text-center transition-colors focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none ${isDragging ? 'border-primary bg-primary/5' : 'border-border hover:bg-muted/50'} disabled:cursor-not-allowed disabled:opacity-60`}
                        >
                            <span className="grid justify-items-center gap-1 text-sm">
                                {currentUpload ? (
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
                        {currentUpload && (
                            <p className="truncate text-xs text-muted-foreground">
                                Cargando {currentUpload.file.name} ·{' '}
                                {formatBytes(currentUpload.file.size)}
                            </p>
                        )}
                        {(uploadQueue.length > 0 || currentUpload) && (
                            <p
                                className="text-xs text-muted-foreground"
                                aria-live="polite"
                            >
                                {currentUpload
                                    ? '1 archivo en proceso'
                                    : 'Preparando carga'}{' '}
                                · {uploadQueue.length} en cola
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
                        {duplicateUploads.map((file) => (
                            <Button
                                key={`${file.name}-${file.size}-${file.lastModified}`}
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => retryDuplicate(file)}
                            >
                                Forzar nuevo análisis de {file.name}
                            </Button>
                        ))}
                        {failedUploads.length > 0 && (
                            <ul
                                className="grid gap-1 text-xs text-destructive"
                                aria-label="Archivos con error"
                            >
                                {failedUploads.map((failure, index) => (
                                    <li key={`${failure.file.name}-${index}`}>
                                        {failure.file.name}: {failure.message}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}

                <div className="grid gap-2">
                    {slot.versions.length === 0 ? (
                        <p className="rounded-lg bg-muted/50 p-3 text-sm text-muted-foreground">
                            Este formato todavía no tiene historial.
                        </p>
                    ) : (
                        slot.versions.map((file) => {
                            const presentation = importFilePresentation({
                                status: file.status,
                                format: file.format,
                                analyzed: file.analyzed,
                                issueCount:
                                    file.issue_counts.error +
                                    file.issue_counts.warning +
                                    file.issue_counts.info,
                            });

                            return (
                                <article
                                    key={file.id}
                                    className={`grid gap-2 rounded-lg border p-3 ${selectedFile?.id === file.id ? 'border-primary bg-primary/5' : ''}`}
                                >
                                    <div className="flex min-w-0 items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <Link
                                                href={show(budgetId, {
                                                    query: selectImportFileQuery(
                                                        currentUrl,
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
                                                {file.year
                                                    ? ` · ${file.year}`
                                                    : ''}
                                            </p>
                                        </div>
                                        <Badge variant="outline">
                                            {presentation.label}
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
                                                {file.issue_counts.error}{' '}
                                                errores
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
                                                                importFile:
                                                                    file.id,
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
                                            presentation.canAnalyze && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled={
                                                        mutationFeedback.activeFileId !==
                                                        null
                                                    }
                                                    onClick={() => {
                                                        setMutationFeedback(
                                                            (current) =>
                                                                startImportMutation(
                                                                    current,
                                                                    file.id,
                                                                ),
                                                        );
                                                        router.post(
                                                            OwnRevenueImportAnalysisController(
                                                                {
                                                                    budget: budgetId,
                                                                    importFile:
                                                                        file.id,
                                                                },
                                                            ),
                                                            {},
                                                            mutationOptions(),
                                                        );
                                                    }}
                                                >
                                                    <Search className="size-3" />{' '}
                                                    Analizar
                                                </Button>
                                            )}
                                        {presentation.canViewIssues && (
                                            <ImportIssueList
                                                budgetId={budgetId}
                                                file={file}
                                                selectedFile={selectedFile}
                                            />
                                        )}
                                        {presentation.canViewPreview && (
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                            >
                                                <Link
                                                    href={preview({
                                                        budget: budgetId,
                                                        importFile: file.id,
                                                    })}
                                                >
                                                    Ver vista previa
                                                </Link>
                                            </Button>
                                        )}
                                        {permissions.manage &&
                                            file.can_reclassify && (
                                                <select
                                                    aria-label={`Corregir tipo de ${file.name}`}
                                                    value={file.format ?? ''}
                                                    disabled={
                                                        mutationFeedback.activeFileId !==
                                                        null
                                                    }
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
                                                    <option value="abpre">
                                                        ABPRE
                                                    </option>
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
                                        {permissions.manage &&
                                            canDiscard(file) && (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="ghost"
                                                    disabled={
                                                        mutationFeedback.activeFileId !==
                                                        null
                                                    }
                                                    onClick={() => {
                                                        if (
                                                            window.confirm(
                                                                `Descartar la versión ${file.version} de ${file.name}?`,
                                                            )
                                                        ) {
                                                            setMutationFeedback(
                                                                (current) =>
                                                                    startImportMutation(
                                                                        current,
                                                                        file.id,
                                                                    ),
                                                            );
                                                            router.delete(
                                                                OwnRevenueImportFileController.destroy(
                                                                    {
                                                                        budget: budgetId,
                                                                        importFile:
                                                                            file.id,
                                                                    },
                                                                ),
                                                                mutationOptions(),
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
                            );
                        })
                    )}
                </div>

                {slot.versions_last_page > 1 && (
                    <nav
                        className="flex items-center justify-between gap-2"
                        aria-label={`Versiones de ${slot.label}`}
                    >
                        {slot.versions_current_page > 1 ? (
                            <Button asChild size="sm" variant="outline">
                                <Link
                                    href={show(budgetId, {
                                        query: queryWith(
                                            currentUrl,
                                            `${slot.format}_versions_page`,
                                            slot.versions_current_page - 1,
                                        ),
                                    })}
                                    preserveScroll
                                >
                                    Anterior
                                </Link>
                            </Button>
                        ) : (
                            <Button size="sm" variant="outline" disabled>
                                Anterior
                            </Button>
                        )}
                        <span className="text-xs text-muted-foreground">
                            Página {slot.versions_current_page} de{' '}
                            {slot.versions_last_page}
                        </span>
                        {slot.versions_has_more ? (
                            <Button asChild size="sm" variant="outline">
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
                        ) : (
                            <Button size="sm" variant="outline" disabled>
                                Siguiente
                            </Button>
                        )}
                    </nav>
                )}
            </CardContent>
        </Card>
    );
}
