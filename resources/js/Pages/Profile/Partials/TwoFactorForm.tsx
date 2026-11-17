import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export type TwoFactorState = { enabled: boolean; pending: boolean; qr: string | null; secret: string | null };

/** Spec 7.0e: set up with a QR code and a code; 10 recovery codes shown once; regenerate or turn off with the password. */
export default function TwoFactorForm({ state, className = '' }: { state: TwoFactorState; className?: string }) {
    const codes = (usePage().props.flash as { recoveryCodes?: string[] | null }).recoveryCodes;
    const password = useForm({ password: '' });
    const confirm = useForm({ code: '' });
    const [action, setAction] = useState<'enable' | 'regenerate' | 'disable' | null>(null);

    const submitPassword: FormEventHandler = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setAction(null), onFinish: () => password.reset() };
        if (action === 'enable') password.post(route('two-factor.enable'), options);
        if (action === 'regenerate') password.post(route('two-factor.recovery-codes'), options);
        if (action === 'disable') password.delete(route('two-factor.disable'), options);
    };
    const submitCode: FormEventHandler = (e) => {
        e.preventDefault();
        confirm.post(route('two-factor.confirm'), { preserveScroll: true, onFinish: () => confirm.reset() });
    };

    const askPassword = (label: string, danger = false) => (
        <form onSubmit={submitPassword} className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
            <div className="flex-1">
                <InputLabel htmlFor="two-factor-password" value="Your password" />
                <TextInput
                    id="two-factor-password"
                    type="password"
                    value={password.data.password}
                    className="mt-1 block w-full"
                    autoComplete="current-password"
                    isFocused
                    onChange={(e) => password.setData('password', e.target.value)}
                    required
                />
                <InputError message={password.errors.password} className="mt-2" />
            </div>
            <div className="flex gap-2">
                <SecondaryButton onClick={() => setAction(null)}>Cancel</SecondaryButton>
                {danger ? <DangerButton disabled={password.processing}>{label}</DangerButton> : <PrimaryButton disabled={password.processing}>{label}</PrimaryButton>}
            </div>
        </form>
    );

    return (
        <section className={className}>
            <header>
                <h2 className="text-heading-2xl text-text">Two-factor authentication</h2>
                <p className="mt-1 text-sm text-muted">
                    {state.enabled
                        ? 'On. Signing in asks for a code from your authenticator app.'
                        : 'Off. Add a code from an authenticator app to your password when you sign in.'}
                </p>
            </header>

            {codes && codes.length > 0 && (
                <div className="mt-4 rounded-card tint-warn p-4" role="status">
                    <p className="text-md">Save these recovery codes somewhere safe. Each works once, and they won’t be shown again.</p>
                    <ul className="mt-3 grid grid-cols-2 gap-x-6 gap-y-1 font-mono text-sm">
                        {codes.map((c) => (
                            <li key={c}>{c}</li>
                        ))}
                    </ul>
                </div>
            )}

            {state.pending && (
                <div className="mt-4 space-y-4">
                    <p className="text-md text-text">Scan this with your authenticator app, then enter the 6-digit code it shows.</p>
                    {state.qr && <img src={state.qr} alt="QR code for your authenticator app" className="h-48 w-48 rounded-ctl" />}
                    <p className="text-sm text-muted">
                        Can’t scan it? Enter this key instead: <code className="break-all text-text">{state.secret}</code>
                    </p>
                    <form onSubmit={submitCode} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div className="flex-1">
                            <InputLabel htmlFor="two-factor-code" value="Code" />
                            <TextInput
                                id="two-factor-code"
                                value={confirm.data.code}
                                className="mt-1 block w-full"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                onChange={(e) => confirm.setData('code', e.target.value)}
                                required
                            />
                            <InputError message={confirm.errors.code} className="mt-2" />
                        </div>
                        <div className="flex gap-2">
                            <SecondaryButton onClick={() => setAction('disable')}>Cancel setup</SecondaryButton>
                            <PrimaryButton disabled={confirm.processing}>Turn on</PrimaryButton>
                        </div>
                    </form>
                    {action === 'disable' && askPassword('Cancel setup')}
                </div>
            )}

            {!state.pending && action === null && (
                <div className="mt-4 flex flex-wrap gap-2">
                    {state.enabled ? (
                        <>
                            <SecondaryButton onClick={() => setAction('regenerate')}>New recovery codes</SecondaryButton>
                            <DangerButton onClick={() => setAction('disable')}>Turn off</DangerButton>
                        </>
                    ) : (
                        <PrimaryButton onClick={() => setAction('enable')}>Set up two-factor</PrimaryButton>
                    )}
                </div>
            )}
            {!state.pending && action === 'enable' && askPassword('Continue')}
            {!state.pending && action === 'regenerate' && askPassword('Make new codes')}
            {!state.pending && action === 'disable' && askPassword('Turn off', true)}
        </section>
    );
}
