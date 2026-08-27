import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
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
import { create, edit, index, show } from '@/routes/rotator';
import type { RotatorListItem } from '@/types';

const numberFormatter = new Intl.NumberFormat();

export default function RotatorsIndex({
    rotators,
    canCreateRotator,
}: {
    rotators: RotatorListItem[];
    canCreateRotator: boolean;
}) {
    return (
        <>
            <Head title="Rotators" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Rotators"
                        description="Every rotator you own, with the traffic it has taken"
                    />

                    {canCreateRotator && (
                        <Button asChild data-test="create-rotator-button">
                            <Link href={create()}>
                                <Plus />
                                New rotator
                            </Link>
                        </Button>
                    )}
                </div>

                {rotators.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-10 text-center">
                        <p className="font-medium">No rotators yet</p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Create one to start splitting traffic across
                            destinations.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Destinations
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Total views
                                    </TableHead>
                                    <TableHead className="text-right">
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {rotators.map((rotator) => (
                                    <TableRow key={rotator.uuid}>
                                        <TableCell>
                                            <Link
                                                href={show(rotator.uuid)}
                                                className="font-medium underline-offset-4 hover:underline"
                                                data-test={`rotator-${rotator.uuid}-link`}
                                            >
                                                {rotator.name}
                                            </Link>
                                            <p className="text-sm text-muted-foreground">
                                                /{rotator.slug}
                                            </p>
                                        </TableCell>
                                        <TableCell>
                                            <RotatorStatusBadge
                                                status={rotator.status}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {numberFormatter.format(
                                                rotator.destinations_count,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right font-medium tabular-nums">
                                            {numberFormatter.format(
                                                rotator.views_count,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                asChild
                                            >
                                                <Link
                                                    href={edit(rotator.uuid)}
                                                    data-test={`edit-rotator-${rotator.uuid}-button`}
                                                >
                                                    Edit
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>
        </>
    );
}

RotatorsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Rotators',
            href: index(),
        },
    ],
};
