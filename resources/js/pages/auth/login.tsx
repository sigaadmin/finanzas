import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { redirect as googleRedirect } from '@/routes/auth/google';

type Props = {
    status?: string;
};

export default function Login({ status }: Props) {
    return (
        <>
            <Head title="Iniciar sesión" />

            <div className="flex flex-col gap-6">
                <Button asChild className="w-full" size="lg">
                    <a href={googleRedirect().url}>Continuar con Google</a>
                </Button>

                <p className="text-center text-sm text-muted-foreground">
                    Usa tu cuenta institucional @crenfcp.edu.mx. El acceso debe
                    estar habilitado previamente por administración.
                </p>
            </div>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-destructive">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Acceso al Portal Financiero',
    description: 'Ingresa con una cuenta autorizada por el CREN',
};
