import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import RotatorForm from '@/components/rotator-form';
import { create, index } from '@/routes/rotator';

export default function CreateRotator() {
    return (
        <>
            <Head title="New rotator" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="New rotator"
                    description="Name the rotator and choose where traffic falls back to"
                />

                <div className="max-w-2xl">
                    <RotatorForm />
                </div>
            </div>
        </>
    );
}

CreateRotator.layout = {
    breadcrumbs: [
        {
            title: 'Rotators',
            href: index(),
        },
        {
            title: 'New rotator',
            href: create(),
        },
    ],
};
