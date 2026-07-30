import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { index, store, update } from '@/routes/authorized-accesses';

type Access = {
    id: number;
    email: string;
    role: string;
    is_active: boolean;
    can_operate_ventanilla: boolean;
    can_operate_u300: boolean;
    can_operate_own_revenue: boolean;
    last_used_at: string | null;
};

type AccessForm = Omit<Access, 'id' | 'last_used_at'>;

const roles = [
    ['admin', 'Administrador'],
    ['finance-manager', 'Responsable financiero'],
    ['finance-assistant', 'Auxiliar financiero'],
    ['finance-auditor', 'Auditor financiero'],
] as const;

const emptyAccess: AccessForm = {
    email: '',
    role: 'finance-assistant',
    is_active: true,
    can_operate_ventanilla: false,
    can_operate_u300: false,
    can_operate_own_revenue: false,
};

function AccessFields({
    data,
    setData,
}: {
    data: AccessForm;
    setData: <K extends keyof AccessForm>(key: K, value: AccessForm[K]) => void;
}) {
    const isAdministrator = data.role === 'admin';

    return (
        <div className="grid gap-4 md:grid-cols-2">
            <label className="grid gap-2 text-sm font-medium">
                Correo institucional
                <Input
                    type="email"
                    value={data.email}
                    onChange={(event) => setData('email', event.target.value)}
                    placeholder="nombre@crenfcp.edu.mx"
                />
            </label>
            <label className="grid gap-2 text-sm font-medium">
                Nivel de acceso
                <select
                    value={data.role}
                    onChange={(event) => setData('role', event.target.value)}
                    className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                >
                    {roles.map(([value, label]) => (
                        <option key={value} value={value}>{label}</option>
                    ))}
                </select>
            </label>
            <div className="md:col-span-2 grid gap-3 rounded-lg border p-4">
                <p className="text-sm font-medium">Módulos habilitados</p>
                {[
                    ['can_operate_ventanilla', 'Ventanilla financiera'],
                    ['can_operate_u300', 'U300'],
                    ['can_operate_own_revenue', 'Ingresos Propios'],
                ].map(([key, label]) => (
                    <label key={key} className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={isAdministrator || data[key as keyof AccessForm] === true}
                            disabled={isAdministrator}
                            onCheckedChange={(checked) => setData(key as keyof AccessForm, checked === true)}
                        />
                        {label}
                    </label>
                ))}
                {isAdministrator && <p className="text-muted-foreground text-xs">Los administradores tienen acceso a todos los módulos.</p>}
            </div>
        </div>
    );
}

export default function Users({ users }: { users: Access[] }) {
    const createForm = useForm<AccessForm>(emptyAccess);
    const [editing, setEditing] = useState<Access | null>(null);
    const editForm = useForm<AccessForm>(emptyAccess);

    function createAccess(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        createForm.post(store().url, { onSuccess: () => createForm.reset() });
    }

    function editAccess(access: Access): void {
        setEditing(access);
        editForm.setData({
            email: access.email, role: access.role, is_active: access.is_active,
            can_operate_ventanilla: access.can_operate_ventanilla,
            can_operate_u300: access.can_operate_u300,
            can_operate_own_revenue: access.can_operate_own_revenue,
        });
    }

    return (
        <>
            <Head title="Usuarios" />
            <div className="space-y-8">
                <Heading variant="small" title="Usuarios" description="Autoriza cuentas institucionales y define los módulos que pueden operar." />
                <form onSubmit={createAccess} className="space-y-4 rounded-xl border p-5">
                    <h2 className="font-semibold">Agregar usuario</h2>
                    <AccessFields data={createForm.data} setData={createForm.setData} />
                    {createForm.errors.email && <p className="text-destructive text-sm">{createForm.errors.email}</p>}
                    <Button disabled={createForm.processing}>Autorizar usuario</Button>
                </form>
                <div className="space-y-3">
                    <h2 className="font-semibold">Usuarios autorizados</h2>
                    {users.map((access) => (
                        <div key={access.id} className="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4">
                            <div>
                                <p className="font-medium">{access.email}</p>
                                <p className="text-muted-foreground text-sm">{roles.find(([value]) => value === access.role)?.[1] ?? 'Owner'} · {access.is_active ? 'Activo' : 'Inactivo'}</p>
                            </div>
                            <Button variant="outline" onClick={() => editAccess(access)} disabled={access.role === 'owner'}>Editar</Button>
                        </div>
                    ))}
                </div>
                {editing && (
                    <form onSubmit={(event) => { event.preventDefault(); editForm.put(update(editing.id).url, { onSuccess: () => setEditing(null) }); }} className="space-y-4 rounded-xl border p-5">
                        <h2 className="font-semibold">Editar {editing.email}</h2>
                        <AccessFields data={editForm.data} setData={editForm.setData} />
                        <label className="flex items-center gap-2 text-sm"><Checkbox checked={editForm.data.is_active} onCheckedChange={(checked) => editForm.setData('is_active', checked === true)} /> Cuenta activa</label>
                        <div className="flex gap-3"><Button disabled={editForm.processing}>Guardar cambios</Button><Button type="button" variant="outline" onClick={() => setEditing(null)}>Cancelar</Button></div>
                    </form>
                )}
            </div>
        </>
    );
}

Users.layout = { breadcrumbs: [{ title: 'Usuarios', href: index() }] };
