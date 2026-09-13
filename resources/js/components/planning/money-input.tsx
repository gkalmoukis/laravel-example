import { Input } from '@/components/ui/input';
import { usePreferences } from '@/hooks/use-preferences';

/**
 * An amount field. The value is sent as typed and parsed on the server, so every format a
 * user might reach for works the same way everywhere.
 */
export default function MoneyInput({
    name,
    defaultCents,
    id,
    ...props
}: {
    name: string;
    defaultCents?: number;
    id?: string;
} & Omit<React.ComponentProps<typeof Input>, 'defaultValue' | 'name' | 'id'>) {
    const { formatAmount } = usePreferences();

    return (
        <Input
            id={id ?? name}
            name={name}
            inputMode="decimal"
            defaultValue={
                defaultCents === undefined
                    ? undefined
                    : formatAmount(defaultCents)
            }
            {...props}
        />
    );
}
