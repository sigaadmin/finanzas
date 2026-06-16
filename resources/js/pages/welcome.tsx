import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { dashboard, login } from '@/routes';

type Props = {
    app: {
        name: string;
    };
    access: {
        domain: string;
        registration_enabled: boolean;
    };
};

export default function Welcome({ app, access }: Props) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title={app.name} />
            <main className="min-h-screen bg-[#f7f8f8] text-[#363334]">
                <div className="mx-auto flex min-h-screen w-full max-w-5xl flex-col px-6 py-8">
                    <header className="flex items-center justify-between gap-4">
                        <div className="flex items-center gap-3">
                            <img
                                src="/images/logo-cren.svg"
                                alt=""
                                className="size-12 object-contain"
                            />
                            <div>
                                <p className="text-sm font-semibold">
                                    Centro Regional de Educación Normal
                                </p>
                                <p className="text-xs text-neutral-600">
                                    Felipe Carrillo Puerto
                                </p>
                            </div>
                        </div>

                        <Link
                            href={auth.user ? dashboard() : login()}
                            className="inline-flex h-9 items-center gap-2 rounded-md bg-[#434596] px-3 text-sm font-medium text-white"
                        >
                            {auth.user ? 'Entrar' : 'Iniciar sesión'}
                            <ArrowRight className="size-4" />
                        </Link>
                    </header>

                    <section className="flex flex-1 items-center py-16">
                        <div className="max-w-3xl">
                            <p className="text-sm font-semibold text-[#434596]">
                                Portal institucional
                            </p>
                            <h1 className="mt-3 text-4xl font-semibold tracking-normal md:text-5xl">
                                {app.name}
                            </h1>
                            <p className="mt-4 max-w-2xl text-base text-neutral-700">
                                Acceso exclusivo para personal autorizado del
                                área financiera del CREN.
                            </p>
                            <p className="mt-6 text-sm text-neutral-500">
                                Dominio institucional: {access.domain}
                            </p>
                        </div>
                    </section>
                </div>
            </main>
        </>
    );
}
