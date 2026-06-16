import { Head, useForm } from '@inertiajs/react';
import { FileUp, LoaderCircle } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import finance from '@/routes/finance';

type ImportFormData = {
    fiscal_year: number;
    catalog_file: File | null;
};

export default function ExpenseClassificationImportCreate() {
    const form = useForm<ImportFormData>({
        fiscal_year: 2026,
        catalog_file: null,
    });

    function submit(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.post(finance.expenseClassifications.imports.store().url, {
            forceFormData: true,
        });
    }

    return (
        <>
            <Head title="Importar catálogo COG" />
            <main className="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
                <header>
                    <p className="text-sm text-muted-foreground">
                        Clasificación por objeto de gasto
                    </p>
                    <h1 className="text-xl font-semibold">
                        Importar catálogo COG
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
                            max={2035}
                            min={2024}
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
                        <Label htmlFor="catalog_file">Archivo XLSX</Label>
                        <Input
                            id="catalog_file"
                            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            type="file"
                            onChange={(event) =>
                                form.setData(
                                    'catalog_file',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                        />
                        <InputError message={form.errors.catalog_file} />
                    </div>

                    <div>
                        <Button disabled={form.processing} type="submit">
                            {form.processing ? (
                                <LoaderCircle className="size-4 animate-spin" />
                            ) : (
                                <FileUp className="size-4" />
                            )}
                            Importar catálogo
                        </Button>
                    </div>
                </form>
            </main>
        </>
    );
}
