import { Head, useForm } from '@inertiajs/react';
import { Check, LoaderCircle, Search, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as storeProcedure } from '@/routes/finance/payment-procedures';
import { search as searchStudents } from '@/routes/finance/students';

type Concept = {
    id: number;
    name: string;
    type: 'internal' | 'external';
    allows_quantity: boolean;
    amount_pesos: number;
};

type SigaStudent = {
    siga_student_id: string;
    matricula: string | null;
    name: string;
    program: string | null;
    grade: string | null;
    group: string | null;
    academic_status: string | null;
};

type ProcedureFormData = {
    student: SigaStudent | null;
    items: Array<{
        charge_concept_id: number;
        quantity: number;
    }>;
};

type Props = {
    concepts: Concept[];
};

const statusLabels: Record<string, string> = {
    active: 'Activa',
    egresada: 'Egresada',
    graduated: 'Egresada',
    inactive: 'Inactiva',
};

function money(amountPesos: number): string {
    return `$${amountPesos.toLocaleString('es-MX')}`;
}

function studentMeta(student: SigaStudent): string {
    return [
        student.matricula,
        student.program,
        [student.grade, student.group].filter(Boolean).join(' '),
        student.academic_status
            ? (statusLabels[student.academic_status] ?? student.academic_status)
            : null,
    ]
        .filter(Boolean)
        .join(' · ');
}

export default function PaymentProcedureCreate({ concepts }: Props) {
    const form = useForm<ProcedureFormData>({
        student: null,
        items: [],
    });
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SigaStudent[]>([]);
    const [isSearching, setIsSearching] = useState(false);
    const [searchError, setSearchError] = useState<string | null>(null);
    const trimmedQuery = query.trim();
    const shouldSearch = trimmedQuery.length >= 2 && !form.data.student;

    useEffect(() => {
        if (!shouldSearch) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            setIsSearching(true);
            setSearchError(null);

            try {
                const response = await fetch(
                    searchStudents.url({
                        query: {
                            q: trimmedQuery,
                        },
                    }),
                    {
                        signal: controller.signal,
                        headers: {
                            Accept: 'application/json',
                        },
                    },
                );

                if (!response.ok) {
                    throw new Error('No se pudo consultar SIGA2.');
                }

                const payload = (await response.json()) as {
                    data: SigaStudent[];
                };

                setResults(payload.data);
            } catch (error) {
                if (!controller.signal.aborted) {
                    setSearchError(
                        error instanceof Error
                            ? error.message
                            : 'No se pudo consultar SIGA2.',
                    );
                }
            } finally {
                if (!controller.signal.aborted) {
                    setIsSearching(false);
                }
            }
        }, 250);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [shouldSearch, trimmedQuery]);

    const selectedConcepts = useMemo(
        () =>
            concepts.filter((concept) =>
                form.data.items.some(
                    (item) => item.charge_concept_id === concept.id,
                ),
            ),
        [concepts, form.data.items],
    );

    const totalPesos = selectedConcepts.reduce(
        (total, concept) =>
            total + concept.amount_pesos * quantityForConcept(concept.id),
        0,
    );

    function quantityForConcept(conceptId: number): number {
        return (
            form.data.items.find(
                (item) => item.charge_concept_id === conceptId,
            )?.quantity ?? 1
        );
    }

    const selectStudent = (student: SigaStudent): void => {
        form.setData('student', student);
        setQuery(student.name);
        setResults([]);
        setSearchError(null);
    };

    const clearStudent = (): void => {
        form.setData('student', null);
        setQuery('');
        setResults([]);
        setSearchError(null);
    };

    const updateQuery = (value: string): void => {
        setQuery(value);

        if (value.trim().length < 2) {
            setResults([]);
            setSearchError(null);
            setIsSearching(false);
        }
    };

    const toggleConcept = (concept: Concept, checked: boolean): void => {
        form.setData(
            'items',
            checked
                ? [
                      ...form.data.items,
                      {
                          charge_concept_id: concept.id,
                          quantity: 1,
                      },
                  ]
                : form.data.items.filter(
                      (item) => item.charge_concept_id !== concept.id,
                  ),
        );
    };

    const updateQuantity = (conceptId: number, quantity: number): void => {
        const safeQuantity = Math.max(1, Math.min(999, quantity || 1));

        form.setData(
            'items',
            form.data.items.map((item) =>
                item.charge_concept_id === conceptId
                    ? { ...item, quantity: safeQuantity }
                    : item,
            ),
        );
    };

    const submit = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        form.post(storeProcedure().url);
    };

    return (
        <>
            <Head title="Nuevo trámite" />
            <main className="flex h-full flex-1 flex-col gap-4 p-4">
                <header>
                    <h1 className="text-xl font-semibold">Nuevo trámite</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Busca una estudiante o egresada en SIGA2, selecciona
                        conceptos y confirma el total.
                    </p>
                </header>

                <form className="grid gap-4" onSubmit={submit}>
                    <section className="grid gap-4 rounded-lg border p-4">
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h2 className="text-sm font-semibold">
                                    Estudiante o egresada
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Elige a la persona que realizará el pago.
                                    Sus datos aparecerán en el recibo.
                                </p>
                            </div>
                            {form.data.student && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={clearStudent}
                                >
                                    <X className="size-4" />
                                    Cambiar
                                </Button>
                            )}
                        </div>

                        <div className="relative grid gap-2">
                            <Label htmlFor="student-search">
                                Buscar por nombre o matrícula
                            </Label>
                            <div className="relative">
                                <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                                <Input
                                    id="student-search"
                                    value={query}
                                    onChange={(event) =>
                                        updateQuery(event.target.value)
                                    }
                                    disabled={form.data.student !== null}
                                    className="pl-9"
                                    placeholder="Escribe al menos 2 caracteres"
                                    autoComplete="off"
                                />
                            </div>
                            <InputError
                                message={
                                    form.errors['student.siga_student_id'] ??
                                    form.errors.student
                                }
                            />

                            {(isSearching ||
                                searchError ||
                                results.length > 0) && (
                                <div className="absolute top-full z-20 mt-1 w-full overflow-hidden rounded-md border bg-background shadow-lg">
                                    {isSearching && (
                                        <div className="flex items-center gap-2 px-3 py-2 text-sm text-muted-foreground">
                                            <LoaderCircle className="size-4 animate-spin" />
                                            Buscando en SIGA2
                                        </div>
                                    )}
                                    {searchError && (
                                        <div className="px-3 py-2 text-sm text-destructive">
                                            {searchError}
                                        </div>
                                    )}
                                    {results.map((student) => (
                                        <button
                                            key={student.siga_student_id}
                                            type="button"
                                            className="grid w-full gap-1 border-t px-3 py-2 text-left text-sm first:border-t-0 hover:bg-muted"
                                            onClick={() =>
                                                selectStudent(student)
                                            }
                                        >
                                            <span className="font-medium">
                                                {student.name}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {studentMeta(student)}
                                            </span>
                                        </button>
                                    ))}
                                    {!isSearching &&
                                        !searchError &&
                                        results.length === 0 &&
                                        query.trim().length >= 2 && (
                                            <div className="px-3 py-2 text-sm text-muted-foreground">
                                                Sin coincidencias.
                                            </div>
                                        )}
                                </div>
                            )}
                        </div>

                        {form.data.student && (
                            <div className="rounded-md border bg-muted/30 p-3 text-sm">
                                <div className="flex items-start gap-2">
                                    <Check className="mt-0.5 size-4 text-primary" />
                                    <div>
                                        <div className="font-medium">
                                            {form.data.student.name}
                                        </div>
                                        <div className="text-muted-foreground">
                                            {studentMeta(form.data.student)}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </section>

                    <section className="rounded-lg border">
                        <div className="border-b px-4 py-3">
                            <h2 className="text-sm font-semibold">
                                Conceptos activos
                            </h2>
                        </div>
                        <div className="divide-y">
                            {concepts.map((concept) => {
                                const isSelected = form.data.items.some(
                                    (item) =>
                                        item.charge_concept_id === concept.id,
                                );
                                const quantity = quantityForConcept(
                                    concept.id,
                                );

                                return (
                                <div
                                    key={concept.id}
                                    className="flex items-center justify-between gap-4 px-4 py-3 text-sm hover:bg-muted/50"
                                >
                                    <div className="flex items-start gap-3">
                                        <Checkbox
                                            checked={isSelected}
                                            onCheckedChange={(checked) =>
                                                toggleConcept(
                                                    concept,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <div>
                                            <div className="font-medium">
                                                {concept.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {concept.type === 'external'
                                                    ? 'Externo · una vez'
                                                    : concept.allows_quantity
                                                      ? 'Interno · permite cantidad'
                                                      : 'Interno · una vez'}
                                            </div>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        {isSelected &&
                                            concept.allows_quantity && (
                                                <div className="flex items-center gap-2">
                                                    <Label
                                                        htmlFor={`quantity-${concept.id}`}
                                                        className="sr-only"
                                                    >
                                                        Cantidad
                                                    </Label>
                                                    <Input
                                                        id={`quantity-${concept.id}`}
                                                        type="number"
                                                        min="1"
                                                        max="999"
                                                        value={quantity}
                                                        className="w-20"
                                                        onChange={(event) =>
                                                            updateQuantity(
                                                                concept.id,
                                                                Number(
                                                                    event
                                                                        .target
                                                                        .value,
                                                                ),
                                                            )
                                                        }
                                                    />
                                                </div>
                                            )}
                                        <span className="font-medium">
                                            {isSelected &&
                                            concept.allows_quantity
                                                ? money(
                                                      concept.amount_pesos *
                                                          quantity,
                                                  )
                                                : money(concept.amount_pesos)}
                                        </span>
                                    </div>
                                </div>
                                );
                            })}
                        </div>
                        <InputError
                            message={form.errors.items}
                            className="px-4 py-2"
                        />
                    </section>

                    <section className="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Total del trámite
                            </p>
                            <p className="text-2xl font-semibold">
                                {money(totalPesos)}
                            </p>
                        </div>
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                form.data.student === null ||
                                form.data.items.length === 0
                            }
                        >
                            Crear trámite
                        </Button>
                    </section>
                </form>
            </main>
        </>
    );
}
