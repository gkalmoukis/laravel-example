import type { ReactNode } from 'react';

/**
 * The description takes a node rather than a string so a screen can explain a financial
 * term where the reader first meets it, rather than in a glossary nobody opens (UX-04).
 */
export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: ReactNode;
    variant?: 'default' | 'small';
}) {
    return (
        <header className={variant === 'small' ? '' : 'mb-8 space-y-0.5'}>
            <h2
                className={
                    variant === 'small'
                        ? 'mb-0.5 text-base font-medium'
                        : 'font-display text-2xl font-normal tracking-tight'
                }
            >
                {title}
            </h2>
            {description && (
                <p className="text-sm text-muted-foreground">{description}</p>
            )}
        </header>
    );
}
