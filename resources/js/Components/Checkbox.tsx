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
                'rounded-[3px] border-input-line bg-surface text-brand focus:ring-brand focus:ring-offset-surface ' +
                className
            }
        />
    );
}
