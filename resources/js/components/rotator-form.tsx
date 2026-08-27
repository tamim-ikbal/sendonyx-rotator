import { Form, Link } from '@inertiajs/react';
import RotatorController from '@/actions/App/Http/Controllers/Rotator/RotatorController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/rotator';
import type { Rotator } from '@/types';

/**
 * The rotator form, shared by the create and edit screens.
 *
 * Both post to the same validation rules on the server, so they render the
 * same fields; only the action and the defaults differ.
 */
export default function RotatorForm({ rotator }: { rotator?: Rotator }) {
    const action = rotator
        ? RotatorController.update.form(rotator.uuid)
        : RotatorController.store.form();

    return (
        <Form
            {...action}
            options={{ preserveScroll: true }}
            className="space-y-6"
            data-test="rotator-form"
        >
            {({ errors, processing }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>

                        <Input
                            id="name"
                            name="name"
                            defaultValue={rotator?.name}
                            required
                            autoComplete="off"
                            placeholder="Summer campaign"
                        />

                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="slug">Slug</Label>

                        <Input
                            id="slug"
                            name="slug"
                            defaultValue={rotator?.slug}
                            autoComplete="off"
                            placeholder="summer-campaign"
                        />

                        <p className="text-sm text-muted-foreground">
                            Letters, numbers, dashes and underscores. Derived
                            from the name when left blank.
                        </p>

                        <InputError message={errors.slug} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="status">Status</Label>

                        <Select
                            name="status"
                            defaultValue={rotator?.status ?? 'active'}
                        >
                            <SelectTrigger
                                id="status"
                                className="w-full"
                                data-test="rotator-status-trigger"
                            >
                                <SelectValue />
                            </SelectTrigger>

                            <SelectContent>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="paused">Paused</SelectItem>
                            </SelectContent>
                        </Select>

                        <InputError message={errors.status} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="default_destination_url">
                            Default destination URL
                        </Label>

                        <Input
                            id="default_destination_url"
                            name="default_destination_url"
                            type="url"
                            defaultValue={
                                rotator?.default_destination_url ?? ''
                            }
                            autoComplete="off"
                            placeholder="https://example.com/offer"
                        />

                        <p className="text-sm text-muted-foreground">
                            Where visitors go when no destination is eligible.
                            Optional.
                        </p>

                        <InputError message={errors.default_destination_url} />
                    </div>

                    <div className="flex items-center gap-4">
                        <Button
                            disabled={processing}
                            data-test="save-rotator-button"
                        >
                            {rotator ? 'Save changes' : 'Create rotator'}
                        </Button>

                        <Button variant="ghost" asChild>
                            <Link href={index()}>Cancel</Link>
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
