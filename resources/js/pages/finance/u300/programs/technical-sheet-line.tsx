import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    Crop,
    LoaderCircle,
    Plus,
    RotateCcw,
    RotateCw,
    Save,
    Trash2,
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
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    emptyU300TechnicalSheetGood,
    parseU300GoodsProfile,
} from '@/lib/u300-goods-profile';
import type { U300TechnicalSheetGood } from '@/lib/u300-goods-profile';
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

type Line = {
    id: number;
    action_number: string;
    action_name: string;
    cog_code: string | null;
    cog_name: string | null;
    chapter_code: string | null;
    chapter_name: string | null;
    amount_cents: number;
    default_scheduled_date: string | null;
    sheet: Sheet | null;
    goods: U300TechnicalSheetGood[];
    uses_goods_list: boolean;
};

type ActionLine = {
    id: number;
    cog_code: string | null;
    cog_name: string | null;
    amount_cents: number;
    is_current: boolean;
};

type Props = {
    program: {
        id: number;
        fiscal_year: number;
        name: string;
    };
    line: Line;
    action_lines: ActionLine[];
};

type LineFormData = Sheet & {
    u300_budget_line_id: number;
    goods: U300TechnicalSheetGood[];
};

type CropSettings = {
    top: number;
    right: number;
    bottom: number;
    left: number;
};

type Rotation = 0 | 90 | 180 | 270;

type CropCorner = 'top-left' | 'top-right' | 'bottom-left' | 'bottom-right';

type CropDrag = {
    corner: CropCorner;
    startX: number;
    startY: number;
    startSettings: CropSettings;
    rect: DOMRect;
};

const defaultCropSettings: CropSettings = {
    top: 0,
    right: 0,
    bottom: 0,
    left: 0,
};

const cropHandles: Array<{
    corner: CropCorner;
    label: string;
    className: string;
}> = [
    {
        corner: 'top-left',
        label: 'Esquina superior izquierda',
        className: '-left-3 -top-3 cursor-nwse-resize',
    },
    {
        corner: 'top-right',
        label: 'Esquina superior derecha',
        className: '-right-3 -top-3 cursor-nesw-resize',
    },
    {
        corner: 'bottom-left',
        label: 'Esquina inferior izquierda',
        className: '-bottom-3 -left-3 cursor-nesw-resize',
    },
    {
        corner: 'bottom-right',
        label: 'Esquina inferior derecha',
        className: '-bottom-3 -right-3 cursor-nwse-resize',
    },
];

function money(cents: number): string {
    return (cents / 100).toLocaleString('es-MX', {
        style: 'currency',
        currency: 'MXN',
    });
}

function pesos(value: string): number {
    const amount = Number(value);

    return Number.isFinite(amount) ? amount : 0;
}

function quantity(value: string): number {
    const amount = Number(value);

    return Number.isFinite(amount) ? amount : 0;
}

function goodTotal(good: U300TechnicalSheetGood): number {
    return quantity(good.minimum_quantity) * pesos(good.unit_price);
}

function rotatedCanvasSize(
    width: number,
    height: number,
    rotation: Rotation,
): { width: number; height: number } {
    return rotation === 90 || rotation === 270
        ? { width: height, height: width }
        : { width, height };
}

function clampCropSide(value: number, opposite: number): number {
    return Math.min(Math.max(value, 0), 95 - opposite);
}

function draggedCropSettings(
    drag: CropDrag,
    clientX: number,
    clientY: number,
): CropSettings {
    const deltaX = ((clientX - drag.startX) / drag.rect.width) * 100;
    const deltaY = ((clientY - drag.startY) / drag.rect.height) * 100;
    const settings = { ...drag.startSettings };

    if (drag.corner.includes('top')) {
        settings.top = clampCropSide(
            drag.startSettings.top + deltaY,
            drag.startSettings.bottom,
        );
    }

    if (drag.corner.includes('bottom')) {
        settings.bottom = clampCropSide(
            drag.startSettings.bottom - deltaY,
            drag.startSettings.top,
        );
    }

    if (drag.corner.includes('left')) {
        settings.left = clampCropSide(
            drag.startSettings.left + deltaX,
            drag.startSettings.right,
        );
    }

    if (drag.corner.includes('right')) {
        settings.right = clampCropSide(
            drag.startSettings.right - deltaX,
            drag.startSettings.left,
        );
    }

    return {
        top: Math.round(settings.top),
        right: Math.round(settings.right),
        bottom: Math.round(settings.bottom),
        left: Math.round(settings.left),
    };
}

function loadImage(url: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const image = new Image();

        if (!url.startsWith('blob:')) {
            image.crossOrigin = 'anonymous';
        }

        image.onload = () => resolve(image);
        image.onerror = () =>
            reject(new Error('No fue posible cargar la foto para recortarla.'));
        image.src = url;
    });
}

async function cropPhotoFile(
    url: string,
    settings: CropSettings,
    rotation: Rotation,
    fileName: string,
): Promise<File> {
    const image = await loadImage(url);
    const originalWidth = image.naturalWidth || image.width;
    const originalHeight = image.naturalHeight || image.height;
    const rotatedSize = rotatedCanvasSize(
        originalWidth,
        originalHeight,
        rotation,
    );
    const rotatedCanvas = document.createElement('canvas');
    const rotatedContext = rotatedCanvas.getContext('2d');

    if (!rotatedContext) {
        throw new Error('No fue posible preparar la rotación de la foto.');
    }

    rotatedCanvas.width = rotatedSize.width;
    rotatedCanvas.height = rotatedSize.height;
    rotatedContext.translate(rotatedSize.width / 2, rotatedSize.height / 2);
    rotatedContext.rotate((rotation * Math.PI) / 180);
    rotatedContext.drawImage(
        image,
        -originalWidth / 2,
        -originalHeight / 2,
        originalWidth,
        originalHeight,
    );

    const sourceWidth = rotatedCanvas.width;
    const sourceHeight = rotatedCanvas.height;
    const cropLeft = Math.round(sourceWidth * (settings.left / 100));
    const cropTop = Math.round(sourceHeight * (settings.top / 100));
    const cropRight = Math.round(sourceWidth * (settings.right / 100));
    const cropBottom = Math.round(sourceHeight * (settings.bottom / 100));
    const cropWidth = Math.max(1, sourceWidth - cropLeft - cropRight);
    const cropHeight = Math.max(1, sourceHeight - cropTop - cropBottom);
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('No fue posible preparar el recorte de la foto.');
    }

    canvas.width = cropWidth;
    canvas.height = cropHeight;
    context.fillStyle = '#fff';
    context.fillRect(0, 0, cropWidth, cropHeight);
    context.drawImage(
        rotatedCanvas,
        cropLeft,
        cropTop,
        cropWidth,
        cropHeight,
        0,
        0,
        cropWidth,
        cropHeight,
    );

    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => {
                if (!blob) {
                    reject(
                        new Error('No fue posible generar la foto recortada.'),
                    );

                    return;
                }

                resolve(
                    new File([blob], fileName, {
                        type: 'image/jpeg',
                    }),
                );
            },
            'image/jpeg',
            0.92,
        );
    });
}

function TextAreaField({
    label,
    value,
    onChange,
    rows = 4,
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
                className="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                rows={rows}
                value={value}
                onFocus={(event) => event.currentTarget.select()}
                onChange={(event) => onChange(event.target.value)}
            />
        </div>
    );
}

export default function U300TechnicalSheetLine({
    program,
    line,
    action_lines,
}: Props) {
    const [editingGoodIndex, setEditingGoodIndex] = useState<number | null>(
        null,
    );
    const [photoPreview, setPhotoPreview] = useState<{
        title: string;
        url: string;
        objectUrl: boolean;
    } | null>(null);
    const [photoCrop, setPhotoCrop] = useState<{
        goodIndex: number;
        title: string;
        url: string;
        objectUrl: boolean;
        settings: CropSettings;
        rotation: Rotation;
    } | null>(null);
    const [photoCropProcessing, setPhotoCropProcessing] = useState(false);
    const [photoCropError, setPhotoCropError] = useState<string | null>(null);
    const [photoCropDrag, setPhotoCropDrag] = useState<CropDrag | null>(null);
    const form = useForm<LineFormData>({
        u300_budget_line_id: line.id,
        item_name: line.sheet?.item_name ?? '',
        objective: line.sheet?.objective ?? '',
        work_description: line.sheet?.work_description ?? '',
        technical_specs: line.sheet?.technical_specs ?? '',
        beneficiaries: line.sheet?.beneficiaries ?? '',
        scheduled_date:
            line.sheet?.scheduled_date ?? line.default_scheduled_date ?? '',
        deliverables: line.sheet?.deliverables ?? '',
        delivery_location: line.sheet?.delivery_location ?? '',
        supervisor: line.sheet?.supervisor ?? '',
        payment_terms: line.sheet?.payment_terms ?? '',
        goods: line.uses_goods_list
            ? line.goods.length > 0
                ? line.goods
                : parseU300GoodsProfile(line.sheet?.technical_specs)
            : [emptyU300TechnicalSheetGood()],
    });
    const validationErrors = form.errors as Record<string, string | undefined>;
    const editingGood =
        editingGoodIndex === null ? null : form.data.goods[editingGoodIndex];
    const goodsSubtotal = form.data.goods.reduce(
        (total, good) => total + goodTotal(good),
        0,
    );
    const goodsAvailable = line.amount_cents / 100 - goodsSubtotal;

    function updateGood(
        index: number,
        updates: Partial<U300TechnicalSheetGood>,
    ): void {
        form.setData(
            'goods',
            form.data.goods.map((good, currentIndex) =>
                currentIndex === index ? { ...good, ...updates } : good,
            ),
        );
    }

    function addGood(): void {
        const nextIndex = form.data.goods.length;
        form.setData('goods', [
            ...form.data.goods,
            emptyU300TechnicalSheetGood(),
        ]);
        setEditingGoodIndex(nextIndex);
    }

    function removeGood(index: number): void {
        form.setData(
            'goods',
            form.data.goods.filter((_, currentIndex) => currentIndex !== index),
        );
    }

    function openPhotoPreview(
        good: U300TechnicalSheetGood,
        title: string,
    ): void {
        if (good.reference_photo) {
            setPhotoPreview({
                title,
                url: URL.createObjectURL(good.reference_photo),
                objectUrl: true,
            });

            return;
        }

        if (good.reference_photo_path !== '') {
            setPhotoPreview({
                title,
                url: `/${good.reference_photo_path.replace(/^\/+/, '')}`,
                objectUrl: false,
            });
        }
    }

    function closePhotoPreview(): void {
        if (photoPreview?.objectUrl) {
            URL.revokeObjectURL(photoPreview.url);
        }

        setPhotoPreview(null);
    }

    function openPhotoCrop(
        good: U300TechnicalSheetGood,
        index: number,
        title: string,
    ): void {
        if (good.reference_photo) {
            setPhotoCrop({
                goodIndex: index,
                title,
                url: URL.createObjectURL(good.reference_photo),
                objectUrl: true,
                settings: defaultCropSettings,
                rotation: 0,
            });
            setPhotoCropError(null);

            return;
        }

        if (good.reference_photo_path !== '') {
            setPhotoCrop({
                goodIndex: index,
                title,
                url: `/${good.reference_photo_path.replace(/^\/+/, '')}`,
                objectUrl: false,
                settings: defaultCropSettings,
                rotation: 0,
            });
            setPhotoCropError(null);
        }
    }

    function closePhotoCrop(): void {
        if (photoCrop?.objectUrl) {
            URL.revokeObjectURL(photoCrop.url);
        }

        setPhotoCrop(null);
        setPhotoCropError(null);
        setPhotoCropProcessing(false);
    }

    function startPhotoCropDrag(
        corner: CropCorner,
        event: React.PointerEvent<HTMLButtonElement>,
    ): void {
        if (!photoCrop) {
            return;
        }

        event.preventDefault();
        event.currentTarget.setPointerCapture(event.pointerId);
        setPhotoCropDrag({
            corner,
            startX: event.clientX,
            startY: event.clientY,
            startSettings: photoCrop.settings,
            rect:
                event.currentTarget
                    .closest('[data-photo-crop-stage]')
                    ?.getBoundingClientRect() ??
                event.currentTarget.getBoundingClientRect(),
        });
    }

    function movePhotoCropDrag(
        event: React.PointerEvent<HTMLButtonElement>,
    ): void {
        if (!photoCrop || !photoCropDrag) {
            return;
        }

        const settings = draggedCropSettings(
            photoCropDrag,
            event.clientX,
            event.clientY,
        );

        setPhotoCrop({
            ...photoCrop,
            settings,
        });
    }

    function stopPhotoCropDrag(): void {
        setPhotoCropDrag(null);
    }

    async function rotatePhotoCrop(direction: 'left' | 'right'): Promise<void> {
        if (!photoCrop) {
            return;
        }

        setPhotoCropProcessing(true);
        setPhotoCropError(null);

        try {
            const file = await cropPhotoFile(
                photoCrop.url,
                defaultCropSettings,
                direction === 'right' ? 90 : 270,
                'foto-referencia-rotada.jpg',
            );
            const rotatedUrl = URL.createObjectURL(file);

            if (photoCrop.objectUrl) {
                URL.revokeObjectURL(photoCrop.url);
            }

            setPhotoCrop({
                ...photoCrop,
                url: rotatedUrl,
                objectUrl: true,
                settings: defaultCropSettings,
                rotation: ((((photoCrop.rotation +
                    (direction === 'right' ? 90 : -90)) %
                    360) +
                    360) %
                    360) as Rotation,
            });
            setPhotoCropDrag(null);
        } catch (error) {
            setPhotoCropError(
                error instanceof Error
                    ? error.message
                    : 'No fue posible rotar la foto.',
            );
        } finally {
            setPhotoCropProcessing(false);
        }
    }

    async function applyPhotoCrop(): Promise<void> {
        if (!photoCrop) {
            return;
        }

        setPhotoCropProcessing(true);
        setPhotoCropError(null);

        try {
            const file = await cropPhotoFile(
                photoCrop.url,
                photoCrop.settings,
                0,
                'foto-referencia-recortada.jpg',
            );

            updateGood(photoCrop.goodIndex, {
                reference_photo: file,
                reference_photo_path: '',
            });
            closePhotoCrop();
        } catch (error) {
            setPhotoCropProcessing(false);
            setPhotoCropError(
                error instanceof Error
                    ? error.message
                    : 'No fue posible recortar la foto.',
            );
        }
    }

    function saveLine(onSuccess?: () => void): void {
        form.transform((data) => ({
            _method: 'put',
            stay_on_page: true,
            return_to_line_id: line.id,
            sheets: [
                {
                    u300_budget_line_id: data.u300_budget_line_id,
                    item_name: data.item_name,
                    objective: data.objective,
                    work_description: data.work_description,
                    technical_specs: line.uses_goods_list
                        ? null
                        : data.technical_specs,
                    beneficiaries: data.beneficiaries,
                    scheduled_date: data.scheduled_date,
                    deliverables: data.deliverables,
                    delivery_location: data.delivery_location,
                    supervisor: data.supervisor,
                    payment_terms: data.payment_terms,
                    goods: line.uses_goods_list ? data.goods : [],
                },
            ],
        }));
        form.post(finance.u300.programs.technicalSheets.update(program).url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess,
        });
    }

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        saveLine();
    }

    function saveGoodAndClose(): void {
        saveLine(() => setEditingGoodIndex(null));
    }

    return (
        <>
            <Head title="Captura de ficha técnica" />
            <main className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <header className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div className="grid gap-1">
                        <p className="text-sm text-muted-foreground">
                            Presupuesto U300 · {program.fiscal_year}
                        </p>
                        <h1 className="text-xl font-semibold">
                            Captura de partida
                        </h1>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link
                                href={finance.u300.programs.technicalSheets.edit(
                                    program,
                                )}
                            >
                                Volver
                            </Link>
                        </Button>
                        <Button
                            disabled={form.processing}
                            form="u300-technical-sheet-line-form"
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

                {action_lines.length > 1 && (
                    <section className="grid gap-3 rounded-lg border p-4">
                        <div className="flex flex-col gap-1">
                            <h2 className="text-sm font-semibold">
                                Partidas de la acción
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {line.action_number} · {line.action_name}
                            </p>
                        </div>
                        <div className="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                            {action_lines.map((actionLine) =>
                                actionLine.is_current ? (
                                    <Button
                                        key={actionLine.id}
                                        className="h-auto justify-start px-3 py-2 text-left whitespace-normal"
                                        disabled
                                        type="button"
                                        variant="secondary"
                                    >
                                        <span className="grid min-w-0 gap-0.5">
                                            <span className="text-xs font-semibold">
                                                {actionLine.cog_code ??
                                                    'Sin clave'}
                                            </span>
                                            <span className="text-sm">
                                                {actionLine.cog_name ??
                                                    'Sin nombre de partida'}
                                            </span>
                                            <span className="text-xs">
                                                {money(actionLine.amount_cents)}
                                            </span>
                                        </span>
                                    </Button>
                                ) : (
                                    <Button
                                        key={actionLine.id}
                                        asChild
                                        className="h-auto justify-start px-3 py-2 text-left whitespace-normal"
                                        variant="outline"
                                    >
                                        <Link
                                            href={finance.u300.programs.technicalSheets.lines.edit(
                                                {
                                                    program,
                                                    line: actionLine.id,
                                                },
                                            )}
                                        >
                                            <span className="grid min-w-0 flex-1 gap-0.5">
                                                <span className="text-xs font-semibold">
                                                    {actionLine.cog_code ??
                                                        'Sin clave'}
                                                </span>
                                                <span className="text-sm">
                                                    {actionLine.cog_name ??
                                                        'Sin nombre de partida'}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {money(
                                                        actionLine.amount_cents,
                                                    )}
                                                </span>
                                            </span>
                                            <ArrowRight className="size-4 shrink-0" />
                                        </Link>
                                    </Button>
                                ),
                            )}
                        </div>
                    </section>
                )}

                <section className="grid gap-3 rounded-lg border p-4 md:grid-cols-3">
                    <div className="grid gap-1">
                        <p className="text-xs font-medium text-muted-foreground">
                            Acción
                        </p>
                        <p className="text-sm font-semibold">
                            {line.action_number} · {line.action_name}
                        </p>
                    </div>
                    <div className="grid gap-1">
                        <p className="text-xs font-medium text-muted-foreground">
                            Partida
                        </p>
                        <p className="text-sm font-semibold">
                            {line.cog_code} · {line.cog_name}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {line.chapter_code} · {line.chapter_name}
                        </p>
                    </div>
                    <div className="grid gap-1">
                        <p className="text-xs font-medium text-muted-foreground">
                            Monto asignado
                        </p>
                        <p className="text-sm font-semibold">
                            {money(line.amount_cents)}
                        </p>
                    </div>
                </section>

                <form
                    id="u300-technical-sheet-line-form"
                    className="grid gap-4"
                    onSubmit={submit}
                >
                    <div className="grid gap-4 lg:grid-cols-2">
                        <TextAreaField
                            label="Título"
                            rows={3}
                            value={form.data.item_name ?? ''}
                            onChange={(value) =>
                                form.setData('item_name', value)
                            }
                        />
                        <TextAreaField
                            label="Trabajos a realizar"
                            value={form.data.work_description ?? ''}
                            onChange={(value) =>
                                form.setData('work_description', value)
                            }
                        />
                        <TextAreaField
                            label="Entregables"
                            value={form.data.deliverables ?? ''}
                            onChange={(value) =>
                                form.setData('deliverables', value)
                            }
                        />
                        <TextAreaField
                            label="Beneficiarios"
                            value={form.data.beneficiaries ?? ''}
                            onChange={(value) =>
                                form.setData('beneficiaries', value)
                            }
                        />
                        <div className="grid gap-2">
                            <Label>Fecha</Label>
                            <Input
                                value={form.data.scheduled_date ?? ''}
                                onChange={(event) =>
                                    form.setData(
                                        'scheduled_date',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>

                    {line.uses_goods_list ? (
                        <section className="grid gap-3 rounded-lg border p-4">
                            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div className="grid gap-1">
                                    <h2 className="text-sm font-semibold">
                                        Bienes a solicitar
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Este listado formará el perfil /
                                        especificaciones técnicas de la ficha.
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={addGood}
                                >
                                    <Plus className="size-4" />
                                    Agregar bien
                                </Button>
                            </div>

                            <div className="grid gap-2 rounded-md border bg-muted/30 p-3 md:grid-cols-3">
                                <div className="grid gap-1">
                                    <p className="text-xs font-medium text-muted-foreground">
                                        Subtotal bienes
                                    </p>
                                    <p className="text-sm font-semibold">
                                        {goodsSubtotal.toLocaleString('es-MX', {
                                            style: 'currency',
                                            currency: 'MXN',
                                        })}
                                    </p>
                                </div>
                                <div className="grid gap-1">
                                    <p className="text-xs font-medium text-muted-foreground">
                                        Monto asignado
                                    </p>
                                    <p className="text-sm font-semibold">
                                        {money(line.amount_cents)}
                                    </p>
                                </div>
                                <div className="grid gap-1">
                                    <p className="text-xs font-medium text-muted-foreground">
                                        Disponible
                                    </p>
                                    <p
                                        className={
                                            goodsAvailable < 0
                                                ? 'text-sm font-semibold text-destructive'
                                                : 'text-sm font-semibold'
                                        }
                                    >
                                        {goodsAvailable.toLocaleString(
                                            'es-MX',
                                            {
                                                style: 'currency',
                                                currency: 'MXN',
                                            },
                                        )}
                                    </p>
                                </div>
                            </div>

                            <div className="overflow-hidden rounded-md border">
                                <div className="grid grid-cols-[1fr_auto] gap-3 border-b bg-muted/40 px-3 py-2 text-xs font-medium text-muted-foreground md:grid-cols-[1fr_8rem_8rem_8rem_auto]">
                                    <span>Bien</span>
                                    <span className="hidden md:block">
                                        Cantidad
                                    </span>
                                    <span className="hidden md:block">
                                        Unitario
                                    </span>
                                    <span className="hidden md:block">
                                        Total
                                    </span>
                                    <span>Acciones</span>
                                </div>
                                {form.data.goods.map((good, index) => (
                                    <div
                                        key={index}
                                        className="grid grid-cols-[1fr_auto] gap-3 border-b px-3 py-3 last:border-b-0 md:grid-cols-[1fr_8rem_8rem_8rem_auto] md:items-center"
                                    >
                                        <div className="grid gap-1">
                                            <p className="text-sm font-medium">
                                                {good.description ||
                                                    `Bien ${index + 1}`}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {good.unit ||
                                                    'Sin unidad de medida'}
                                            </p>
                                            {(good.reference_photo ||
                                                good.reference_photo_path) && (
                                                <div className="flex flex-wrap gap-2">
                                                    <Button
                                                        className="w-fit px-2 py-1 text-xs"
                                                        type="button"
                                                        variant="outline"
                                                        onClick={() =>
                                                            openPhotoPreview(
                                                                good,
                                                                good.description ||
                                                                    `Bien ${index + 1}`,
                                                            )
                                                        }
                                                    >
                                                        Ver foto
                                                    </Button>
                                                    <Button
                                                        className="w-fit px-2 py-1 text-xs"
                                                        type="button"
                                                        variant="outline"
                                                        onClick={() =>
                                                            openPhotoCrop(
                                                                good,
                                                                index,
                                                                good.description ||
                                                                    `Bien ${index + 1}`,
                                                            )
                                                        }
                                                    >
                                                        <Crop className="size-3" />
                                                        Editar foto
                                                    </Button>
                                                </div>
                                            )}
                                        </div>
                                        <p className="hidden text-sm md:block">
                                            {good.minimum_quantity || '0'}
                                        </p>
                                        <p className="hidden text-sm md:block">
                                            {pesos(
                                                good.unit_price,
                                            ).toLocaleString('es-MX', {
                                                style: 'currency',
                                                currency: 'MXN',
                                            })}
                                        </p>
                                        <p className="hidden text-sm font-medium md:block">
                                            {goodTotal(good).toLocaleString(
                                                'es-MX',
                                                {
                                                    style: 'currency',
                                                    currency: 'MXN',
                                                },
                                            )}
                                        </p>
                                        <div className="flex gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    setEditingGoodIndex(index)
                                                }
                                            >
                                                Editar
                                            </Button>
                                            <Button
                                                disabled={
                                                    form.data.goods.length === 1
                                                }
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    removeGood(index)
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    ) : (
                        <TextAreaField
                            label="Perfil / especificaciones técnicas"
                            rows={6}
                            value={form.data.technical_specs ?? ''}
                            onChange={(value) =>
                                form.setData('technical_specs', value)
                            }
                        />
                    )}

                    <InputError message={validationErrors.sheets} />
                </form>

                <Dialog
                    open={editingGoodIndex !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setEditingGoodIndex(null);
                        }
                    }}
                >
                    <DialogContent className="sm:max-w-3xl">
                        <DialogHeader>
                            <DialogTitle>
                                {editingGoodIndex === null
                                    ? 'Bien'
                                    : `Bien ${editingGoodIndex + 1}`}
                            </DialogTitle>
                            <DialogDescription>
                                Captura los datos del bien que se agregará al
                                perfil / especificaciones técnicas.
                            </DialogDescription>
                        </DialogHeader>

                        {editingGood && editingGoodIndex !== null && (
                            <div className="grid gap-4">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label>Unidad de medida</Label>
                                        <Input
                                            value={editingGood.unit}
                                            onChange={(event) =>
                                                updateGood(editingGoodIndex, {
                                                    unit: event.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <TextAreaField
                                        label="Descripción del bien"
                                        rows={3}
                                        value={editingGood.description}
                                        onChange={(value) =>
                                            updateGood(editingGoodIndex, {
                                                description: value,
                                            })
                                        }
                                    />
                                    <div className="grid gap-2">
                                        <Label>Cantidad mínima</Label>
                                        <Input
                                            inputMode="decimal"
                                            value={editingGood.minimum_quantity}
                                            onChange={(event) =>
                                                updateGood(editingGoodIndex, {
                                                    minimum_quantity:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Precio unitario</Label>
                                        <Input
                                            inputMode="decimal"
                                            value={editingGood.unit_price}
                                            onChange={(event) =>
                                                updateGood(editingGoodIndex, {
                                                    unit_price:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                </div>

                                <TextAreaField
                                    label="Especificaciones"
                                    rows={5}
                                    value={editingGood.specifications}
                                    onChange={(value) =>
                                        updateGood(editingGoodIndex, {
                                            specifications: value,
                                        })
                                    }
                                />

                                <div className="grid gap-2">
                                    <Label>Foto de referencia</Label>
                                    <Input
                                        accept="image/jpeg,image/png"
                                        type="file"
                                        onChange={(event) =>
                                            updateGood(editingGoodIndex, {
                                                reference_photo:
                                                    event.target.files?.[0] ??
                                                    null,
                                                reference_photo_path: '',
                                            })
                                        }
                                    />
                                    {(editingGood.reference_photo ||
                                        editingGood.reference_photo_path) && (
                                        <div className="flex flex-wrap gap-2">
                                            <Button
                                                className="w-fit"
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    openPhotoPreview(
                                                        editingGood,
                                                        editingGood.description ||
                                                            `Bien ${editingGoodIndex + 1}`,
                                                    )
                                                }
                                            >
                                                Ver foto
                                            </Button>
                                            <Button
                                                className="w-fit"
                                                type="button"
                                                variant="outline"
                                                onClick={() =>
                                                    openPhotoCrop(
                                                        editingGood,
                                                        editingGoodIndex,
                                                        editingGood.description ||
                                                            `Bien ${editingGoodIndex + 1}`,
                                                    )
                                                }
                                            >
                                                <Crop className="size-4" />
                                                Editar foto
                                            </Button>
                                        </div>
                                    )}
                                </div>

                                <div className="grid gap-2">
                                    <Label>Total</Label>
                                    <Input
                                        readOnly
                                        value={goodTotal(
                                            editingGood,
                                        ).toLocaleString('es-MX', {
                                            style: 'currency',
                                            currency: 'MXN',
                                        })}
                                    />
                                </div>
                            </div>
                        )}

                        <DialogFooter>
                            <Button
                                disabled={form.processing}
                                type="button"
                                onClick={saveGoodAndClose}
                            >
                                {form.processing && (
                                    <LoaderCircle className="size-4 animate-spin" />
                                )}
                                Listo
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={photoPreview !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            closePhotoPreview();
                        }
                    }}
                >
                    <DialogContent className="sm:max-w-3xl">
                        <DialogHeader>
                            <DialogTitle>
                                {photoPreview?.title ?? 'Foto de referencia'}
                            </DialogTitle>
                            <DialogDescription>
                                Foto de referencia del bien seleccionado.
                            </DialogDescription>
                        </DialogHeader>
                        {photoPreview && (
                            <div className="overflow-hidden rounded-md border bg-muted/20">
                                <img
                                    alt="Foto de referencia del bien"
                                    className="max-h-[70vh] w-full object-contain"
                                    src={photoPreview.url}
                                />
                            </div>
                        )}
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={photoCrop !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            closePhotoCrop();
                        }
                    }}
                >
                    <DialogContent className="sm:max-w-4xl">
                        <DialogHeader>
                            <DialogTitle>
                                Recortar foto
                                {photoCrop ? ` · ${photoCrop.title}` : ''}
                            </DialogTitle>
                            <DialogDescription>
                                Ajusta los márgenes para quitar espacio
                                innecesario de la foto de referencia.
                            </DialogDescription>
                        </DialogHeader>

                        {photoCrop && (
                            <div className="grid gap-4">
                                <div className="flex flex-col gap-2 rounded-md border bg-background p-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            disabled={photoCropProcessing}
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                void rotatePhotoCrop('left')
                                            }
                                        >
                                            <RotateCcw className="size-4" />
                                            Rotar izquierda
                                        </Button>
                                        <Button
                                            disabled={photoCropProcessing}
                                            type="button"
                                            variant="outline"
                                            onClick={() =>
                                                void rotatePhotoCrop('right')
                                            }
                                        >
                                            <RotateCw className="size-4" />
                                            Rotar derecha
                                        </Button>
                                    </div>
                                    <div className="flex flex-wrap gap-2 sm:justify-end">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={closePhotoCrop}
                                        >
                                            Cancelar
                                        </Button>
                                        <Button
                                            disabled={photoCropProcessing}
                                            type="button"
                                            onClick={() =>
                                                void applyPhotoCrop()
                                            }
                                        >
                                            {photoCropProcessing && (
                                                <LoaderCircle className="size-4 animate-spin" />
                                            )}
                                            Aplicar recorte
                                        </Button>
                                    </div>
                                </div>

                                <div className="overflow-auto rounded-md border bg-muted/20 p-3">
                                    <div
                                        className="relative mx-auto w-fit overflow-hidden"
                                        data-photo-crop-image-frame
                                        data-photo-crop-stage
                                    >
                                        <img
                                            alt="Vista previa del recorte"
                                            className="block max-h-[65vh] max-w-full select-none"
                                            draggable={false}
                                            src={photoCrop.url}
                                        />

                                        <div
                                            className="absolute border-2 border-white outline outline-2 outline-ring"
                                            style={{
                                                inset: `${photoCrop.settings.top}% ${photoCrop.settings.right}% ${photoCrop.settings.bottom}% ${photoCrop.settings.left}%`,
                                                boxShadow:
                                                    '0 0 0 9999px rgb(0 0 0 / 45%)',
                                            }}
                                        >
                                            {cropHandles.map((handle) => (
                                                <button
                                                    key={handle.corner}
                                                    aria-label={handle.label}
                                                    className={`absolute size-6 rounded-full border-2 border-white bg-ring shadow-md ${handle.className}`}
                                                    type="button"
                                                    onPointerCancel={
                                                        stopPhotoCropDrag
                                                    }
                                                    onPointerDown={(event) =>
                                                        startPhotoCropDrag(
                                                            handle.corner,
                                                            event,
                                                        )
                                                    }
                                                    onPointerMove={
                                                        movePhotoCropDrag
                                                    }
                                                    onPointerUp={
                                                        stopPhotoCropDrag
                                                    }
                                                />
                                            ))}
                                        </div>
                                    </div>
                                </div>

                                <div className="grid gap-1 text-sm text-muted-foreground">
                                    <p>
                                        Arrastra las esquinas del marco para
                                        quitar espacio innecesario de la foto.
                                    </p>
                                    <p>
                                        Recorte: arriba {photoCrop.settings.top}
                                        %, abajo {photoCrop.settings.bottom}%,
                                        izquierda {photoCrop.settings.left}%,
                                        derecha {photoCrop.settings.right}%.
                                    </p>
                                </div>
                                <InputError
                                    message={photoCropError ?? undefined}
                                />
                            </div>
                        )}
                    </DialogContent>
                </Dialog>
            </main>
        </>
    );
}
