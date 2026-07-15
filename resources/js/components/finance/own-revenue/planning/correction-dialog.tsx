import { router } from '@inertiajs/react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { PlanningSelectedDetail } from '@/types/finance-own-revenue';

export default function CorrectionDialog({
    detail,
}: {
    detail: PlanningSelectedDetail;
}) {
    return (
        <Dialog
            open={detail !== null}
            onOpenChange={(open) => {
                if (!open) {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('detail_id');
                    router.visit(`${url.pathname}${url.search}`, {
                        preserveScroll: true,
                    });
                }
            }}
        >
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Correcciones registradas</DialogTitle>
                    <DialogDescription>{detail?.title}</DialogDescription>
                </DialogHeader>
                <div className="grid gap-3">
                    {detail?.corrections.map((correction) => (
                        <article
                            key={correction.id}
                            className="grid gap-2 rounded-lg border p-4 text-sm"
                        >
                            <p className="font-medium">{correction.field}</p>
                            <div className="grid gap-2 sm:grid-cols-2">
                                <p>
                                    <span className="block text-xs text-muted-foreground">
                                        Valor anterior
                                    </span>
                                    {correction.old_value}
                                </p>
                                <p>
                                    <span className="block text-xs text-muted-foreground">
                                        Valor confirmado
                                    </span>
                                    {correction.new_value}
                                </p>
                            </div>
                            <p>
                                <span className="block text-xs text-muted-foreground">
                                    Motivo
                                </span>
                                {correction.justification}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Registrado por {correction.actor_name}
                            </p>
                        </article>
                    ))}
                    {detail?.corrections.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            Este registro no tiene correcciones manuales.
                        </p>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
