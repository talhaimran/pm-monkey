import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { slugify } from '@/slug';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Props {
    invitation: { token: string; email: string; workspace: string } | null;
    createsWorkspace: boolean;
    closedMessage: string | null;
}

// Spec 7.0a: an invitation joins its workspace; on Cloud you otherwise name a new one; self-hosted closes after the first user.
export default function Register({
    invitation,
    createsWorkspace,
    closedMessage,
}: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: invitation?.email ?? '',
        password: '',
        password_confirmation: '',
        invitation: invitation?.token ?? '',
        workspace_name: '',
        workspace_slug: '',
    });
    const [slugEdited, setSlugEdited] = useState(false);
    const formErrors = errors as typeof errors & { invitation?: string };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Register" />

            {closedMessage ? (
                <div className="text-center">
                    <h1 className="text-heading-2xl text-text">
                        Registration is by invitation
                    </h1>
                    <p className="mt-2 text-muted">{closedMessage}</p>
                    <Link
                        href={route('login')}
                        className="mt-4 inline-block rounded-ctl text-sm text-muted underline hover:text-text"
                    >
                        Already registered? Log in
                    </Link>
                </div>
            ) : (
                <form onSubmit={submit}>
                    {invitation && (
                        <p className="mb-4 rounded-item tint-info px-3 py-2 text-sm text-text">
                            You're joining{' '}
                            <strong>{invitation.workspace}</strong>.
                        </p>
                    )}
                    <InputError
                        message={formErrors.invitation}
                        className="mb-4"
                    />

                    <div>
                        <InputLabel htmlFor="name" value="Name" />

                        <TextInput
                            id="name"
                            name="name"
                            value={data.name}
                            className="mt-1 block w-full"
                            autoComplete="name"
                            isFocused={true}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />

                        <InputError message={errors.name} className="mt-2" />
                    </div>

                    <div className="mt-4">
                        <InputLabel htmlFor="email" value="Email" />

                        <TextInput
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            className="mt-1 block w-full"
                            autoComplete="username"
                            onChange={(e) => setData('email', e.target.value)}
                            readOnly={!!invitation}
                            required
                        />

                        <InputError message={errors.email} className="mt-2" />
                    </div>

                    <div className="mt-4">
                        <InputLabel htmlFor="password" value="Password" />

                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            value={data.password}
                            className="mt-1 block w-full"
                            autoComplete="new-password"
                            onChange={(e) =>
                                setData('password', e.target.value)
                            }
                            required
                        />

                        <InputError
                            message={errors.password}
                            className="mt-2"
                        />
                    </div>

                    <div className="mt-4">
                        <InputLabel
                            htmlFor="password_confirmation"
                            value="Confirm password"
                        />

                        <TextInput
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            value={data.password_confirmation}
                            className="mt-1 block w-full"
                            autoComplete="new-password"
                            onChange={(e) =>
                                setData('password_confirmation', e.target.value)
                            }
                            required
                        />

                        <InputError
                            message={errors.password_confirmation}
                            className="mt-2"
                        />
                    </div>

                    {createsWorkspace && (
                        <>
                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="workspace_name"
                                    value="Workspace name"
                                />
                                <TextInput
                                    id="workspace_name"
                                    name="workspace_name"
                                    value={data.workspace_name}
                                    className="mt-1 block w-full"
                                    maxLength={100}
                                    onChange={(e) =>
                                        setData((d) => ({
                                            ...d,
                                            workspace_name: e.target.value,
                                            workspace_slug: slugEdited
                                                ? d.workspace_slug
                                                : slugify(e.target.value),
                                        }))
                                    }
                                    required
                                />
                                <InputError
                                    message={errors.workspace_name}
                                    className="mt-2"
                                />
                            </div>
                            <div className="mt-4">
                                <InputLabel
                                    htmlFor="workspace_slug"
                                    value="Workspace address"
                                />
                                <div className="mt-1 flex items-center gap-1 text-muted">
                                    <span aria-hidden="true">/</span>
                                    <TextInput
                                        id="workspace_slug"
                                        name="workspace_slug"
                                        value={data.workspace_slug}
                                        className="block w-full"
                                        maxLength={40}
                                        aria-describedby="workspace_slug_hint"
                                        onChange={(e) => {
                                            setSlugEdited(true);
                                            setData(
                                                'workspace_slug',
                                                e.target.value.toLowerCase(),
                                            );
                                        }}
                                    />
                                </div>
                                <p
                                    id="workspace_slug_hint"
                                    className="mt-1 text-sm text-muted"
                                >
                                    3–40 lowercase letters, digits or hyphens.
                                </p>
                                <InputError
                                    message={errors.workspace_slug}
                                    className="mt-2"
                                />
                            </div>
                        </>
                    )}

                    <div className="mt-4 flex items-center justify-end">
                        <Link
                            href={route('login')}
                            className="rounded-ctl text-sm text-muted underline hover:text-text"
                        >
                            Already registered?
                        </Link>

                        <PrimaryButton className="ms-4" disabled={processing}>
                            Register
                        </PrimaryButton>
                    </div>
                </form>
            )}
        </GuestLayout>
    );
}
