import { ButtonHTMLAttributes } from 'react';

export default function DangerButton({
    className = '',
    disabled,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...props}
            className={
                'inline-flex h-control-h items-center justify-center gap-1.5 rounded-ctl px-4 text-md transition-colors duration-instant disabled:cursor-not-allowed disabled:opacity-50 bg-c-bad text-on-ink hover:opacity-90 ' + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
}
