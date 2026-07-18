import { useForm } from '@inertiajs/react';
import { CircleCheck, LoaderCircle, TriangleAlert } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { reset } from '@/routes/local-data';
import { canSubmitLocalDataReset } from './local-data-reset-state.js';

export type LocalDataResetScopeDefinition = {
    value: 'ventanilla' | 'u300' | 'own-revenue' | 'all';
    label: string;
    description: string;
    preserves: string[];
    confirmation_phrase: string;
    is_global: boolean;
};

export default function LocalDataResetCard({
    scope,
    className,
}: {
    scope: LocalDataResetScopeDefinition;
    className?: string;
}) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const form = useForm({ confirmation: '' });
    const canSubmit = canSubmitLocalDataReset(
        form.data.confirmation,
        scope.confirmation_phrase,
        form.processing,
    );

    const changeDialogOpen = (open: boolean): void => {
        setDialogOpen(open);

        if (!open) {
            form.reset();
            form.clearErrors();
        }
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (!canSubmit) {
            return;
        }

        form.post(reset(scope.value).url, {
            preserveScroll: true,
            onSuccess: () => changeDialogOpen(false),
        });
    };

    return (
        <Card
            className={cn(
                'h-full gap-5',
                scope.is_global && 'border-destructive/40',
                className,
            )}
        >
            <CardHeader>
                <div className="flex items-start justify-between gap-3">
                    <CardTitle>{scope.label}</CardTitle>
                    {scope.is_global && (
                        <TriangleAlert
                            className="size-5 shrink-0 text-destructive"
                            aria-hidden="true"
                        />
                    )}
                </div>
                <CardDescription className="leading-relaxed">
                    {scope.description}
                </CardDescription>
            </CardHeader>

            <CardContent className="grow space-y-3">
                <p className="text-sm font-medium">Se conserva</p>
                <ul className="space-y-2 text-sm text-muted-foreground">
                    {scope.preserves.map((item) => (
                        <li key={item} className="flex items-start gap-2">
                            <CircleCheck
                                className="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                                aria-hidden="true"
                            />
                            <span>{item}</span>
                        </li>
                    ))}
                </ul>
            </CardContent>

            <CardFooter>
                <Dialog open={dialogOpen} onOpenChange={changeDialogOpen}>
                    <DialogTrigger asChild>
                        <Button
                            type="button"
                            variant={
                                scope.is_global ? 'destructive' : 'outline'
                            }
                        >
                            Preparar reinicio
                        </Button>
                    </DialogTrigger>

                    <DialogContent>
                        <form onSubmit={submit} className="space-y-5">
                            <DialogHeader>
                                <DialogTitle>
                                    Reiniciar {scope.label}
                                </DialogTitle>
                                <DialogDescription>
                                    {scope.description} Esta operación no se
                                    puede deshacer.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-2">
                                <Label htmlFor={`confirmation-${scope.value}`}>
                                    Escriba la frase exactamente como aparece
                                </Label>
                                <p className="text-sm font-semibold">
                                    {scope.confirmation_phrase}
                                </p>
                                <Input
                                    id={`confirmation-${scope.value}`}
                                    value={form.data.confirmation}
                                    onChange={(event) =>
                                        form.setData(
                                            'confirmation',
                                            event.target.value,
                                        )
                                    }
                                    autoComplete="off"
                                    autoFocus
                                    aria-invalid={Boolean(
                                        form.errors.confirmation,
                                    )}
                                />
                                <InputError
                                    message={form.errors.confirmation}
                                />
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => changeDialogOpen(false)}
                                    disabled={form.processing}
                                >
                                    Volver
                                </Button>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={!canSubmit}
                                >
                                    {form.processing && (
                                        <LoaderCircle className="animate-spin" />
                                    )}
                                    {form.processing
                                        ? 'Reiniciando…'
                                        : 'Reiniciar ahora'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </CardFooter>
        </Card>
    );
}
