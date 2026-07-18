import { Head } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import Heading from '@/components/heading';
import LocalDataResetCard, {
    type LocalDataResetScopeDefinition,
} from '@/components/settings/local-data-reset-card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { index } from '@/routes/local-data';

export default function LocalData({
    scopes,
}: {
    scopes: LocalDataResetScopeDefinition[];
}) {
    return (
        <>
            <Head title="Datos locales" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Datos locales"
                    description="Reinicia únicamente los datos de prueba que necesites volver a capturar"
                />

                <Alert>
                    <ShieldAlert aria-hidden="true" />
                    <AlertTitle>Disponible sólo en esta instalación</AlertTitle>
                    <AlertDescription>
                        Cada opción indica qué información elimina y qué se
                        conserva. Los cambios no se pueden deshacer.
                    </AlertDescription>
                </Alert>

                <div className="grid gap-4 md:grid-cols-2">
                    {scopes.map((scope) => (
                        <LocalDataResetCard
                            key={scope.value}
                            scope={scope}
                            className={
                                scope.is_global ? 'md:col-span-2' : undefined
                            }
                        />
                    ))}
                </div>
            </div>
        </>
    );
}

LocalData.layout = {
    breadcrumbs: [
        {
            title: 'Datos locales',
            href: index(),
        },
    ],
};
