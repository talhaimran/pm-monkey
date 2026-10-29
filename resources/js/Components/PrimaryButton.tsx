import { ButtonHTMLAttributes } from 'react';

export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...props}
            className={
                'inline-flex h-8 items-center justify-center gap-1 rounded-[3px] px-3 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-1 focus-visible:ring-offset-surface disabled:cursor-not-allowed disabled:opacity-50 bg-brand text-on-brand hover:bg-brand-hover ' + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
