import { Head, useForm } from '@inertiajs/react';
import { FileUp, LoaderCircle } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import finance from '@/routes/finance';

type ImportFormData = {
    fiscal_year: number;
    project_pdf: File | null;
};

export default function U300ImportCreate() {
    const form = useForm<ImportFormData>({
        fiscal_year: 2026,
        project_pdf: null,
    });

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.post(finance.u300.imports.preview().url, {
            forceFormData: true,
        });
    }

    return (
        <>
            <Head title="Importar proyecto U300" />
            <main className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <header className="flex flex-col gap-1">
                    <p className="text-sm text-muted-foreground">
                        Presupuesto U300 / PROFEXCE
                    </p>
                    <h1 className="text-xl font-semibold">
                        Importar proyecto federal
                    </h1>
                </header>

                <form
                    onSubmit={submit}
                    className="grid max-w-2xl gap-5 rounded-lg border p-4"
                >
                    <div className="grid gap-2">
                        <Label htmlFor="fiscal_year">Ciclo fiscal</Label>
                        <Input
                            id="fiscal_year"
                            min={2024}
                            max={2035}
                            type="number"
                            value={form.data.fiscal_year}
                            onChange={(event) =>
                                form.setData(
                                    'fiscal_year',
                                    Number(event.target.value),
                                )
                            }
                        />
                        <InputError message={form.errors.fiscal_year} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="project_pdf">PDF del proyecto</Label>
                        <Input
                            id="project_pdf"
                            accept="application/pdf,.pdf"
                            type="file"
                            onChange={(event) =>
                                form.setData(
                                    'project_pdf',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <InputError message={form.errors.project_pdf} />
                    </div>

                    <div>
                        <Button disabled={form.processing} type="submit">
                            {form.processing ? (
                                <LoaderCircle className="size-4 animate-spin" />
                            ) : (
                                <FileUp className="size-4" />
                            )}
                            Revisar importación
                        </Button>
                    </div>
                </form>
            </main>
        </>
    );
}
