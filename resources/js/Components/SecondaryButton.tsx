import { ButtonHTMLAttributes, forwardRef } from 'react';

export default forwardRef<HTMLButtonElement, ButtonHTMLAttributes<HTMLButtonElement>>(function SecondaryButton({
    type = 'button',
    className = '',
    disabled,
    children,
    ...props
}, ref) {
    return (
        <button
            {...props}
            ref={ref}
            type={type}
            className={
                'inline-flex h-control-h items-center justify-center gap-1.5 rounded-ctl px-4 text-md transition-colors duration-instant disabled:cursor-not-allowed disabled:opacity-50 border border-line-2 bg-card text-text hover:bg-card-2 ' + className
            }
            disabled={disabled}
        >
            {children}
        </button>
    );
});
