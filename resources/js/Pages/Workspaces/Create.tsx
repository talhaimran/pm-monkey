import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { slugify } from '@/slug';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

/** Cloud only: another workspace, owned by the person creating it. */
export default function Create() {
    const back = usePage().props.workspace;
    const { data, setData, post, processing, errors } = useForm({ name: '', slug: '' });
    const [slugEdited, setSlugEdited] = useState(false);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('workspaces.store'));
    };

    return (
        <GuestLayout>
            <Head title="Create workspace" />
            <h1 className="text-heading-2xl text-text">Create workspace</h1>
            <p className="mt-1 text-muted">You'll be its Owner. Invite people once it's ready.</p>

            <form onSubmit={submit} className="mt-6 space-y-4">
                <div>
                    <InputLabel htmlFor="name" value="Workspace name" />
                    <TextInput
                        id="name"
                        value={data.name}
                        className="mt-1 block w-full"
                        isFocused
                        maxLength={100}
                        onChange={(e) => setData((d) => ({ name: e.target.value, slug: slugEdited ? d.slug : slugify(e.target.value) }))}
                        required
                    />
                    <InputError message={errors.name} className="mt-2" />
                </div>
                <div>
                    <InputLabel htmlFor="slug" value="Address" />
                    <div className="mt-1 flex items-center gap-1 text-muted">
                        <span aria-hidden="true">/</span>
                        <TextInput
                            id="slug"
                            value={data.slug}
                            className="block w-full"
                            maxLength={40}
                            aria-describedby="slug-hint"
                            onChange={(e) => {
                                setSlugEdited(true);
                                setData('slug', e.target.value.toLowerCase());
                            }}
                        />
                    </div>
                    <p id="slug-hint" className="mt-1 text-sm text-muted">
                        3–40 lowercase letters, digits or hyphens.
                    </p>
                    <InputError message={errors.slug} className="mt-2" />
                </div>
                <div className="flex items-center justify-end gap-4">
                    {back && (
                        <Link href={route('dashboard')} className="rounded-ctl text-sm text-muted underline hover:text-text">
                            Cancel
                        </Link>
                    )}
                    <PrimaryButton disabled={processing}>Create workspace</PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
