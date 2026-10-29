import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { IssueOptions } from '@/types';
import { useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export const selectClass =
    'rounded-[3px] border-input-line bg-surface text-sm text-ink placeholder:text-ink-subtle hover:bg-sunken focus:border-brand focus:bg-surface focus:ring-1 focus:ring-brand py-1.5 pe-8';

export default function IssueFormModal({
    show,
    onClose,
    projectKey,
    options,
    parent,
}: {
    show: boolean;
    onClose: () => void;
    projectKey: string;
    options: IssueOptions;
    parent?: string;
}) {
    const form = useForm({
        title: '',
        description: '',
        type: 'task',
        priority: 'medium',
        status: options.columns[0] ?? '',
        assignee: '',
        labels: [] as string[],
        story_points: '',
        due_date: '',
        parent: parent ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        form.transform((d) => ({
            ...d,
            assignee: d.assignee || null,
            story_points: d.story_points === '' ? null : Number(d.story_points),
            due_date: d.due_date || null,
            parent: d.parent || null,
        }));
        form.post(route('projects.issues.store', projectKey), {
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    const toggleLabel = (name: string) =>
        form.setData(
            'labels',
            form.data.labels.includes(name)
                ? form.data.labels.filter((l) => l !== name)
                : [...form.data.labels, name],
        );

    const field = (label: string, name: string, input: React.ReactNode, error?: string) => (
        <div>
            <InputLabel htmlFor={name} value={label} />
            <div className="mt-1">{input}</div>
            <InputError className="mt-1" message={error} />
        </div>
    );

    return (
        <Modal show={show} onClose={onClose} maxWidth="2xl">
            <form onSubmit={submit} className="space-y-4 p-6">
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {parent ? `New sub-task of ${parent}` : `New issue in ${projectKey}`}
                </h2>

                {field(
                    'Title',
                    'title',
                    <TextInput
                        id="title"
                        className="block w-full"
                        value={form.data.title}
                        onChange={(e) => form.setData('title', e.target.value)}
                        isFocused
                        required
                    />,
                    form.errors.title,
                )}

                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    {field(
                        'Type',
                        'type',
                        <select id="type" className={`${selectClass} w-full`} value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                            {options.types.filter((t) => !parent || t !== 'epic').map((t) => (
                                <option key={t}>{t}</option>
                            ))}
                        </select>,
                        form.errors.type,
                    )}
                    {field(
                        'Priority',
                        'priority',
                        <select id="priority" className={`${selectClass} w-full`} value={form.data.priority} onChange={(e) => form.setData('priority', e.target.value)}>
                            {options.priorities.map((p) => (
                                <option key={p}>{p}</option>
                            ))}
                        </select>,
                        form.errors.priority,
                    )}
                    {field(
                        'Status',
                        'status',
                        <select id="status" className={`${selectClass} w-full`} value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}>
                            {options.columns.map((c) => (
                                <option key={c}>{c}</option>
                            ))}
                        </select>,
                        form.errors.status,
                    )}
                    {field(
                        'Assignee',
                        'assignee',
                        <select id="assignee" className={`${selectClass} w-full`} value={form.data.assignee} onChange={(e) => form.setData('assignee', e.target.value)}>
                            <option value="">Unassigned</option>
                            {options.members.map((m) => (
                                <option key={m.email} value={m.email}>
                                    {m.name}
                                </option>
                            ))}
                        </select>,
                        form.errors.assignee,
                    )}
                </div>

                <div className="grid grid-cols-2 gap-4">
                    {field(
                        'Story points',
                        'story_points',
                        <TextInput id="story_points" type="number" min={0} className="block w-full" value={form.data.story_points} onChange={(e) => form.setData('story_points', e.target.value)} />,
                        form.errors.story_points,
                    )}
                    {field(
                        'Due date',
                        'due_date',
                        <TextInput id="due_date" type="date" className="block w-full" value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} />,
                        form.errors.due_date,
                    )}
                </div>

                {options.labels.length > 0 && (
                    <fieldset>
                        <legend className="text-sm font-medium text-gray-700 dark:text-gray-300">Labels</legend>
                        <div className="mt-1 flex flex-wrap gap-2">
                            {options.labels.map((l) => (
                                <label key={l.id} className="flex items-center gap-1 text-sm text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" className="rounded" checked={form.data.labels.includes(l.name)} onChange={() => toggleLabel(l.name)} />
                                    {l.name}
                                </label>
                            ))}
                        </div>
                        <InputError className="mt-1" message={form.errors.labels} />
                    </fieldset>
                )}

                {field(
                    'Description (Markdown)',
                    'description',
                    <textarea id="description" rows={5} className={`${selectClass} w-full`} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />,
                    form.errors.description,
                )}
                <InputError message={form.errors.parent} />

                <div className="flex justify-end gap-3">
                    <SecondaryButton onClick={onClose}>Cancel</SecondaryButton>
                    <PrimaryButton disabled={form.processing}>Create</PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
