import { InputHTMLAttributes } from 'react';

export default function Checkbox({
    className = '',
    ...props
}: InputHTMLAttributes<HTMLInputElement>) {
    return (
        <input
            {...props}
            type="checkbox"
            className={
                'h-4 w-4 rounded-xs border-line-2 bg-card text-primary focus:ring-0 focus:ring-offset-0 ' +
                className
            }
        />
    );
}
