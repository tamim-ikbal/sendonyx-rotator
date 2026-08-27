import { Head, Link, setLayoutProps } from '@inertiajs/react';
import Heading from '@/components/heading';
import RotatorForm from '@/components/rotator-form';
import { Button } from '@/components/ui/button';
import { edit, index, show } from '@/routes/rotator';
import type { Rotator } from '@/types';

export default function EditRotator({ rotator }: { rotator: Rotator }) {
    // The trail names the rotator, so it cannot be the static object the other
    // pages declare: it is only known once the props arrive.
    setLayoutProps({
        breadcrumbs: [
            { title: 'Rotators', href: index() },
            { title: rotator.name, href: show(rotator.uuid) },
            { title: 'Edit', href: edit(rotator.uuid) },
        ],
    });

    return (
        <>
            <Head title={`Edit ${rotator.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={`Edit ${rotator.name}`}
                        description="Changes take effect on the next visitor"
                    />

                    <Button variant="outline" asChild>
                        <Link href={show(rotator.uuid)}>View traffic</Link>
                    </Button>
                </div>

                <div className="max-w-2xl">
                    <RotatorForm rotator={rotator} />
                </div>
            </div>
        </>
    );
}
