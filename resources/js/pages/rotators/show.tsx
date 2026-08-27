import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import Heading from '@/components/heading';
import RotatorStatusBadge from '@/components/rotator-status-badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { edit, index, show } from '@/routes/rotator';
import type { Rotator, RotatorDestination } from '@/types';

const numberFormatter = new Intl.NumberFormat();

const shareFormatter = new Intl.NumberFormat(undefined, {
    style: 'percent',
    maximumFractionDigits: 1,
});

type Props = {
    rotator: Rotator;
    totalViews: number;
    destinations: RotatorDestination[];
};

function share(views: number, total: number): string {
    return total === 0 ? '—' : shareFormatter.format(views / total);
}

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border p-4">
            <p className="text-sm text-muted-foreground">{label}</p>
            <p className="mt-1 text-2xl font-semibold tabular-nums">{value}</p>
        </div>
    );
}

export default function ShowRotator({
    rotator,
    totalViews,
    destinations,
}: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Rotators', href: index() },
            { title: rotator.name, href: show(rotator.uuid) },
        ],
    });

    const attributedViews = destinations.reduce(
        (total, destination) => total + destination.views_count,
        0,
    );

    // Whatever the destinations did not take went to the default url, so the
    // rows and this remainder always account for the headline figure.
    const fallbackViews = totalViews - attributedViews;

    const activeCount = destinations.filter(
        (destination) => destination.status === 'active',
    ).length;

    return (
        <>
            <Head title={rotator.name} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex flex-col gap-2">
                        <Heading
                            title={rotator.name}
                            description={`/${rotator.slug}`}
                        />
                        <RotatorStatusBadge status={rotator.status} />
                    </div>

                    <Button asChild data-test="edit-rotator-button">
                        <Link href={edit(rotator.uuid)}>
                            <Pencil />
                            Edit rotator
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Stat
                        label="Total views"
                        value={numberFormatter.format(totalViews)}
                    />
                    <Stat
                        label="Destinations"
                        value={numberFormatter.format(destinations.length)}
                    />
                    <Stat
                        label="In rotation"
                        value={numberFormatter.format(activeCount)}
                    />
                </div>

                <div className="space-y-4">
                    <Heading
                        variant="small"
                        title="Destinations"
                        description="Clicks each destination received, excluding bot traffic"
                    />

                    {destinations.length === 0 ? (
                        <div className="rounded-xl border border-dashed p-10 text-center">
                            <p className="font-medium">No destinations yet</p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Until one is added, every visitor falls through
                                to the default destination URL.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto rounded-xl border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Destination</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">
                                            Weight
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Clicks
                                        </TableHead>
                                        <TableHead className="text-right">
                                            Share
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>

                                <TableBody>
                                    {destinations.map((destination) => (
                                        <TableRow key={destination.uuid}>
                                            <TableCell className="max-w-md truncate font-medium">
                                                {destination.url}
                                            </TableCell>
                                            <TableCell>
                                                <RotatorStatusBadge
                                                    status={destination.status}
                                                />
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {destination.weight}
                                            </TableCell>
                                            <TableCell
                                                className="text-right font-medium tabular-nums"
                                                data-test={`destination-${destination.uuid}-clicks`}
                                            >
                                                {numberFormatter.format(
                                                    destination.views_count,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right text-muted-foreground tabular-nums">
                                                {share(
                                                    destination.views_count,
                                                    totalViews,
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}

                                    {fallbackViews > 0 && (
                                        <TableRow>
                                            <TableCell className="max-w-md truncate text-muted-foreground italic">
                                                {rotator.default_destination_url ??
                                                    'Default destination URL'}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                Fallback
                                            </TableCell>
                                            <TableCell className="text-right text-muted-foreground">
                                                —
                                            </TableCell>
                                            <TableCell className="text-right font-medium text-muted-foreground tabular-nums">
                                                {numberFormatter.format(
                                                    fallbackViews,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right text-muted-foreground tabular-nums">
                                                {share(
                                                    fallbackViews,
                                                    totalViews,
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
