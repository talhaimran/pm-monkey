import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-card-2 pt-6 sm:justify-center sm:pt-0">
            <div>
                <Link href="/" className="flex items-center gap-3">
                    <ApplicationLogo className="h-10 w-10 fill-current text-primary" />
                    <span className="text-2xl font-semibold text-text">PmOps</span>
                </Link>
            </div>

            <div className="mt-6 w-full overflow-hidden bg-bg px-6 py-8 shadow-xs sm:max-w-md sm:px-10 sm:rounded sm:border sm:border-line">
                {children}
            </div>
        </div>
    );
}
