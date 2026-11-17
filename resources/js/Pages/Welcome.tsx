import ApplicationLogo from '@/Components/ApplicationLogo';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';

const button = 'inline-flex h-control-h items-center rounded-ctl px-4 text-md';

export default function Welcome({ auth }: PageProps) {
    return (
        <>
            <Head title="Welcome" />
            <main className="flex min-h-screen flex-col items-center justify-center gap-6 bg-bg p-6 text-center">
                <ApplicationLogo className="h-12 w-auto fill-current text-primary" />
                <h1 className="text-display text-text">PmOps</h1>
                <p className="max-w-md text-muted">A calm, fast issue tracker where people and AI agents work the same board.</p>
                <div className="flex gap-2">
                    {auth.user ? (
                        <Link href="/dashboard" className={`${button} bg-primary text-on-primary`}>
                            Open your work
                        </Link>
                    ) : (
                        <>
                            <Link href={route('login')} className={`${button} bg-primary text-on-primary`}>
                                Log in
                            </Link>
                            <Link href={route('register')} className={`${button} border border-line-2 bg-card text-text`}>
                                Register
                            </Link>
                        </>
                    )}
                </div>
            </main>
        </>
    );
}
