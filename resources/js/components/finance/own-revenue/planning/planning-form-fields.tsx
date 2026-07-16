import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export const selectClassName =
    'border-input bg-background focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]';

export function PlanningField({
    label,
    name,
    error,
    children,
}: {
    label: string;
    name: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={name}>{label}</Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

export function PlanningInput({
    label,
    name,
    error,
    ...props
}: React.ComponentProps<typeof Input> & {
    label: string;
    name: string;
    error?: string;
}) {
    return (
        <PlanningField label={label} name={name} error={error}>
            <Input id={name} name={name} {...props} />
        </PlanningField>
    );
}
