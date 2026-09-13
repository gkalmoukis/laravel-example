import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePreferences } from '@/hooks/use-preferences';
import { addDays } from '@/lib/dates';

/**
 * When it happened.
 *
 * Almost every transaction is recorded the day it happened or the day after, and a bare
 * date field made both of those a date-picker interaction. Two buttons cover the common
 * case in one tap and the field is still there for everything else (UX-01, §2.3).
 */
export default function QuickAddDate({
    value,
    onChange,
    error,
    warning,
}: {
    value: string;
    onChange: (date: string) => void;
    error?: string;
    warning?: string;
}) {
    const { today } = usePreferences();

    const now = today();
    const yesterday = addDays(now, -1);

    const shortcuts = [
        { label: 'Today', date: now, testId: 'date-today' },
        { label: 'Yesterday', date: yesterday, testId: 'date-yesterday' },
    ];

    return (
        <div>
            <Label htmlFor="occurred_on">Date</Label>

            <div className="mt-1 flex flex-wrap items-center gap-2">
                {shortcuts.map((shortcut) => (
                    <Button
                        key={shortcut.label}
                        type="button"
                        size="sm"
                        variant={
                            value === shortcut.date ? 'default' : 'outline'
                        }
                        aria-pressed={value === shortcut.date}
                        onClick={() => onChange(shortcut.date)}
                        data-testid={shortcut.testId}
                    >
                        {shortcut.label}
                    </Button>
                ))}

                <Input
                    id="occurred_on"
                    name="occurred_on"
                    type="date"
                    className="h-11 w-auto flex-1 md:h-9"
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    aria-invalid={Boolean(error)}
                />
            </div>

            <InputError message={error} />

            {warning !== undefined && (
                <p
                    className="mt-1 text-xs text-status-warning"
                    data-testid="no-plan-warning"
                >
                    {warning}
                </p>
            )}
        </div>
    );
}
