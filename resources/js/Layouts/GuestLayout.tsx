import ApplicationLogo from '@/Components/ApplicationLogo';
import PlanLimitDialog from '@/Components/PlanLimitDialog';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-bg px-4 pt-6 sm:justify-center sm:pt-0">
            <div>
                <Link href="/" className="flex items-center gap-3">
                    <ApplicationLogo className="h-10 w-10 fill-current text-primary" />
                    <span className="text-heading-3xl text-text">PmOps</span>
                </Link>
            </div>

            <div className="mt-6 w-full overflow-hidden rounded-card bg-card px-6 py-8 shadow sm:max-w-md sm:px-10">
                {children}
            </div>
            {/* Accepting an invitation can hit a plan limit before the person is in the workspace. */}
            <PlanLimitDialog />
        </div>
    );
}
