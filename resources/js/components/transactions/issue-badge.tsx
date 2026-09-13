import { AlertTriangle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { TransactionIssue } from '@/types/transactions';

/**
 * Why a transaction is not being counted (TXV-05).
 *
 * The reason is on the badge itself rather than behind the tooltip alone, because a
 * warning the user cannot read on a phone is a warning they will ignore.
 */
export default function IssueBadge({ issues }: { issues: TransactionIssue[] }) {
    if (issues.length === 0) {
        return null;
    }

    const reasons = issues.map((issue) => issue.reason).join(' ');

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Badge
                    variant="outline"
                    className="gap-1 border-amber-500/50 text-amber-700 dark:text-amber-400"
                    data-testid="issue-badge"
                >
                    <AlertTriangle className="size-3" aria-hidden="true" />
                    Not counted
                </Badge>
            </TooltipTrigger>
            <TooltipContent className="max-w-xs">{reasons}</TooltipContent>
        </Tooltip>
    );
}
