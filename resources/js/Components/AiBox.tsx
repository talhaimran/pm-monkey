import ApprovalCard from '@/Components/ApprovalCard';
import { ApprovalRequest } from '@/types';

/**
 * The AI box on the issue page and in the peek drawer (spec 5.1). Step A has pending approval requests only; later
 * steps add the latest agent run and "Ask agent". Shows nothing when there is nothing to show; never a summary.
 */
export default function AiBox({ approvals, columns }: { approvals?: (ApprovalRequest & { can_decide?: boolean })[]; columns: string[] }) {
    if (!approvals?.length) return null;

    return (
        <section aria-labelledby="ai-box-heading" className="space-y-2">
            <h2 id="ai-box-heading" className="text-sm font-semibold text-muted">
                Waiting for approval
            </h2>
            {approvals.map((a) => (
                <ApprovalCard key={a.id} approval={a} canDecide={!!a.can_decide} columns={columns} />
            ))}
        </section>
    );
}
