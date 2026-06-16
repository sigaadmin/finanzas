import { Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { login } from '@/routes';

export default function Register() {
    return (
        <>
            <Head title="Registro no disponible" />
            <div className="grid gap-4 text-center text-sm">
                <p className="text-muted-foreground">
                    El registro público está deshabilitado. Solicita acceso a la administración del CREN.
                </p>
                <TextLink href={login()} tabIndex={1}>
                    Volver al inicio de sesión
                </TextLink>
            </div>
        </>
    );
}

Register.layout = {
    title: 'Registro no disponible',
    description: 'El acceso se controla desde el padrón interno autorizado',
};
