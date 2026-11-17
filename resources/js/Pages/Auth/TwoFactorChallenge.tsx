import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

/** Spec 7.0e: the second step of sign-in, with a code from the app or one recovery code. */
export default function TwoFactorChallenge() {
    const [recovery, setRecovery] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ code: '', recovery_code: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('two-factor.login.store'), { onFinish: () => reset() });
    };
    const field = recovery ? 'recovery_code' : 'code';

    return (
        <GuestLayout>
            <Head title="Two-factor authentication" />
            <h1 className="text-heading-2xl text-text">Two-factor authentication</h1>
            <p className="mt-1 text-sm text-muted">
                {recovery ? 'Enter one of your recovery codes. Each one works once.' : 'Enter the 6-digit code from your authenticator app.'}
            </p>

            <form onSubmit={submit} className="mt-6 space-y-4">
                <div>
                    <InputLabel htmlFor={field} value={recovery ? 'Recovery code' : 'Code'} />
                    <TextInput
                        key={field}
                        id={field}
                        value={data[field]}
                        className="mt-1 block w-full"
                        autoComplete="one-time-code"
                        inputMode={recovery ? 'text' : 'numeric'}
                        isFocused
                        onChange={(e) => setData(field, e.target.value)}
                        required
                    />
                    <InputError message={errors[field]} className="mt-2" />
                </div>
                <div className="flex items-center justify-between gap-4">
                    <button
                        type="button"
                        className="rounded-ctl text-sm text-muted underline hover:text-text"
                        onClick={() => {
                            setRecovery(!recovery);
                            reset();
                        }}
                    >
                        {recovery ? 'Use a code from the app' : 'Use a recovery code'}
                    </button>
                    <PrimaryButton disabled={processing}>Sign in</PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
