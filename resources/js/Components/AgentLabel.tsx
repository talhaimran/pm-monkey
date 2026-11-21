import Avatar from '@/Components/Avatar';

/** Where an agent acted: the agent avatar always beside words, the agent's name and whose agent it is. */
export default function AgentLabel({ agent, size = 'xs', className = '' }: { agent: { name: string; owner: string }; size?: 'xs' | 'sm' | 'md'; className?: string }) {
    return (
        <span className={`inline-flex min-w-0 items-center gap-2 ${className}`}>
            <Avatar agent name={agent.name} size={size} />
            <span className="truncate">
                <strong className="font-semibold text-text">{agent.name}</strong> <span className="text-muted">{agent.owner}’s agent</span>
            </span>
        </span>
    );
}
