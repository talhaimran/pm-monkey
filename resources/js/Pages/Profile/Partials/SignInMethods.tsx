import InputError from '@/Components/InputError';
import SecondaryButton from '@/Components/SecondaryButton';
import { router, usePage } from '@inertiajs/react';

export type SignInState = { hasPassword: boolean; providers: string[]; linked: Record<string, string> };

const names: Record<string, string> = { google: 'Google', github: 'GitHub' };

/** Spec 7.0f: link or unlink Google and GitHub; the last way to sign in can't be removed. */
export default function SignInMethods({ state, className = '' }: { state: SignInState; className?: string }) {
    const errors = usePage().props.errors as Record<string, string>;
    // Providers this install offers, plus any still linked from before a provider was switched off.
    const shown = [...new Set([...state.providers, ...Object.keys(state.linked)])];

    return (
        <section className={className}>
            <header>
                <h2 className="text-heading-2xl text-text">Sign-in methods</h2>
                <p className="mt-1 text-sm text-muted">
                    {state.hasPassword ? 'You can sign in with your password.' : 'You have no password yet. To set one, sign out and use “Forgot your password?”.'}
                </p>
            </header>
            <ul className="mt-4 divide-y divide-line">
                {shown.map((p) => (
                    <li key={p} className="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div>
                            <div className="text-md text-text">{names[p] ?? p}</div>
                            <div className="text-sm text-muted">{state.linked[p] ? `Linked to ${state.linked[p]}` : 'Not linked'}</div>
                        </div>
                        {state.linked[p] ? (
                            <SecondaryButton onClick={() => router.delete(route('social.unlink', p), { preserveScroll: true })}>Unlink</SecondaryButton>
                        ) : (
                            state.providers.includes(p) && (
                                <a href={route('social.redirect', p)} className="flex h-control-h items-center rounded-ctl border border-line-2 bg-card px-4 text-md text-text hover:bg-card-2">
                                    Link {names[p] ?? p}
                                </a>
                            )
                        )}
                    </li>
                ))}
            </ul>
            <InputError message={errors.social} className="mt-2" />
        </section>
    );
}
