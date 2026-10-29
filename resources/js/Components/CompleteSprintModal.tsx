import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { selectClass } from '@/Components/IssueFormModal';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { useForm } from '@inertiajs/react';

/** Confirms completing the active sprint and picks where unfinished issues go (backlog or a planned sprint). */
export default function CompleteSprintModal({
    project,
    sprint,
    planned,
    onClose,
}: {
    project: { key: string };
    sprint: { id: number; name: string } | null;
    planned: string[];
    onClose: () => void;
}) {
    const form = useForm({ confirm: true, move_open_issues_to: 'backlog' });

    return (
        <Modal show={sprint !== null} onClose={onClose}>
            {sprint && (
                <form
                    className="space-y-4 p-6"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(route('projects.sprints.complete', [project.key, sprint.id]), { preserveScroll: true, onSuccess: onClose });
                    }}
                >
                    <h2 className="text-xl font-semibold text-ink">Complete {sprint.name}?</h2>
                    <p className="text-ink-subtle">
                        Done issues stay with the sprint. Unfinished issues move to the destination below. A completed sprint can't be reopened.
                    </p>
                    <div>
                        <InputLabel htmlFor="move-to" value="Move unfinished issues to" />
                        <select
                            id="move-to"
                            className={`${selectClass} mt-1 w-full`}
                            value={form.data.move_open_issues_to}
                            onChange={(e) => form.setData('move_open_issues_to', e.target.value)}
                        >
                            <option value={'backlog'}>Backlog</option>
                            {planned.map((name) => (
                                <option key={name} value={name}>
                                    {name}
                                </option>
                            ))}
                        </select>
                        <InputError className="mt-1" message={form.errors.move_open_issues_to ?? (form.errors as Record<string, string>).sprint} />
                    </div>
                    <div className="flex justify-end gap-3">
                        <SecondaryButton onClick={onClose}>Cancel</SecondaryButton>
                        <PrimaryButton disabled={form.processing}>Complete sprint</PrimaryButton>
                    </div>
                </form>
            )}
        </Modal>
    );
}
