import type { ReactNode } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * The plain-language explanations from the specification, word for word (§5.3, UX-04).
 *
 * These are not paraphrased anywhere. The wording was chosen to explain a financial term
 * to someone who does not already know it, and rewriting it per screen is how a product
 * ends up explaining the same word three slightly different ways.
 */
export const GLOSSARY = {
    plan: 'What you expect to earn or spend. You set it; you can change it any time.',
    actual: 'What really happened, calculated from the transactions you recorded.',
    forecast:
        'Our best guess for the whole year: real numbers for finished months, your plan for the rest.',
    variance: 'The difference between Actual and Plan.',
    cashFlow: 'Money in minus money out for a period.',
    closingBalance: 'How much money you have at the end of a month.',
    savings: 'What is left after expenses: income minus expenses.',
    savingsRate:
        'The share of your income you keep. Savings divided by income.',
    emergencyFund:
        'Money set aside to cover your essential expenses for a few months if your income stops.',
    netWorth: 'Everything you own minus everything you owe.',
    irregularExpense:
        "A cost that doesn't happen every month, such as holidays or annual insurance.",
    monthStatus:
        'Whether you have finished recording a month. Only finished months count as final.',
} as const;

export type GlossaryKey = keyof typeof GLOSSARY;

/**
 * A financial term the reader may not know, explained where they meet it (UX-04).
 *
 * Marked once per screen, on first appearance: a dotted underline on every occurrence of
 * "Plan" would turn the page into a minefield of tooltips.
 *
 * The explanation is in the accessible tree as well as the tooltip, because a tooltip
 * that only opens on hover tells a screen reader and a touch user nothing. The trigger is
 * a button so it is reachable and openable from the keyboard (NFR-04).
 */
export default function GlossaryTerm({
    term,
    children,
}: {
    term: GlossaryKey;
    children: ReactNode;
}) {
    const explanation = GLOSSARY[term];

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <button
                    type="button"
                    className="cursor-help underline decoration-dotted decoration-from-font underline-offset-4"
                    data-testid={`glossary-${term}`}
                >
                    {children}
                    <span className="sr-only"> — {explanation}</span>
                </button>
            </TooltipTrigger>

            <TooltipContent className="max-w-64">{explanation}</TooltipContent>
        </Tooltip>
    );
}
