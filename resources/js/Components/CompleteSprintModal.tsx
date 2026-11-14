import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { selectClass } from '@/Components/IssueFormModal';
import WarningConfirm from '@/Components/WarningConfirm';
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
        <WarningConfirm
            open={sprint !== null}
            title={`Complete ${sprint?.name ?? 'sprint'}?`}
            warnings={[
                { text: 'Unfinished issues move to the destination below.', reason: 'Done issues stay with the sprint.' },
                { text: "A completed sprint can't be reopened." },
            ]}
            confirmLabel="Complete sprint"
            processing={form.processing}
            onCancel={onClose}
            onConfirm={() => sprint && form.post(route('projects.sprints.complete', [project.key, sprint.id]), { preserveScroll: true, onSuccess: onClose })}
        >
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
        </WarningConfirm>
    );
}
