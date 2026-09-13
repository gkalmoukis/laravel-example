import { Head, Link, usePage } from '@inertiajs/react';
import { CalendarRange, Receipt, Telescope } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

/**
 * The only page anyone sees before signing in (§1).
 *
 * There is no public registration and there never will be — accounts exist only by
 * invitation — so this says what Fin is and offers exactly one way in. Anything that
 * looked like a sign-up would be a promise the application cannot keep.
 */
export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="A private finance planner" />

            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <header className="mx-auto flex w-full max-w-5xl items-center justify-between px-4 py-6 md:px-6">
                    {/*
                     * A wordmark rather than the starter kit's Laravel mark, which is
                     * not this product's identity to wear.
                     */}
                    <span className="font-display text-xl">Fin</span>

                    <Button asChild variant={auth.user ? 'default' : 'outline'}>
                        <Link href={auth.user ? dashboard() : login()}>
                            {auth.user ? 'Go to dashboard' : 'Sign in'}
                        </Link>
                    </Button>
                </header>

                <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col justify-center px-4 py-12 md:px-6 lg:py-20">
                    <p className="text-sm font-medium tracking-wide text-primary uppercase">
                        Private · invitation only
                    </p>

                    <h1 className="mt-4 max-w-2xl font-display text-4xl leading-tight font-normal tracking-tight text-balance sm:text-5xl lg:text-6xl">
                        Your year, planned, recorded and forecast in one place.
                    </h1>

                    <p className="mt-6 max-w-xl text-lg text-muted-foreground">
                        Fin replaces the spreadsheet. Plan what you expect to
                        earn and spend, record what actually happens, and see
                        where the year is heading — without needing to know any
                        accounting.
                    </p>

                    <div className="mt-10">
                        <Button asChild size="lg">
                            <Link href={auth.user ? dashboard() : login()}>
                                {auth.user ? 'Go to dashboard' : 'Sign in'}
                            </Link>
                        </Button>
                    </div>

                    <dl className="mt-16 grid gap-8 sm:grid-cols-3">
                        <Pitch
                            icon={CalendarRange}
                            title="Plan"
                            body="Set the year's income, budget and irregular costs once. Change any of it whenever you like."
                        />
                        <Pitch
                            icon={Receipt}
                            title="Record"
                            body="Enter a transaction in seconds. Every actual figure, balance and variance is worked out for you."
                        />
                        <Pitch
                            icon={Telescope}
                            title="Forecast"
                            body="Real numbers for the months you have finished, your plan for the rest, and the year-end position."
                        />
                    </dl>
                </main>

                <footer className="mx-auto w-full max-w-5xl px-4 py-8 text-sm text-muted-foreground md:px-6">
                    Accounts are created by invitation. If you are expecting
                    one, it arrives by email.
                </footer>
            </div>
        </>
    );
}

function Pitch({
    icon: Icon,
    title,
    body,
}: {
    icon: typeof Receipt;
    title: string;
    body: string;
}) {
    return (
        <div>
            <dt className="flex items-center gap-2 font-medium">
                <Icon className="size-5 text-primary" aria-hidden="true" />
                {title}
            </dt>
            <dd className="mt-2 text-sm text-muted-foreground">{body}</dd>
        </div>
    );
}
