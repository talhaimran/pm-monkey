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
                'rounded-[3px] border-line-2 bg-bg text-primary focus:ring-primary focus:ring-offset-bg ' +
                className
            }
        />
    );
}
