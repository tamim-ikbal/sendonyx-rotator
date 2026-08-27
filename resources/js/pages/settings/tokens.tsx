import { Form, Head } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { toast } from 'sonner';
import ApiTokenController from '@/actions/App/Http/Controllers/Settings/ApiTokenController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useClipboard } from '@/hooks/use-clipboard';
import { edit } from '@/routes/api-tokens';

type ApiToken = {
    id: number;
    name: string;
    last_used_at: string | null;
    created_at: string | null;
};

type Props = {
    tokens: ApiToken[];
    createdToken: string | null;
};

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
});

function formatDate(value: string | null, fallback: string): string {
    return value ? dateFormatter.format(new Date(value)) : fallback;
}

function CreatedToken({ token }: { token: string }) {
    const [copiedText, copy] = useClipboard();

    const handleCopy = async () => {
        if (await copy(token)) {
            toast.success('Token copied to your clipboard.');
        }
    };

    return (
        <div className="space-y-4 rounded-lg border border-amber-100 bg-amber-50 p-4 dark:border-amber-200/10 dark:bg-amber-700/10">
            <div className="space-y-0.5 text-amber-700 dark:text-amber-100">
                <p className="font-medium">Copy your token now</p>
                <p className="text-sm">
                    This is the only time it will be shown. Only a hash of it is
                    stored, so it cannot be recovered later.
                </p>
            </div>

            <div className="flex items-center gap-2">
                <code
                    data-test="created-token"
                    className="flex-1 overflow-x-auto rounded-md border bg-background px-3 py-2 font-mono text-xs"
                >
                    {token}
                </code>

                <Button
                    type="button"
                    variant="secondary"
                    onClick={() => void handleCopy()}
                    data-test="copy-token-button"
                >
                    {copiedText === token ? <Check /> : <Copy />}
                    <span className="sr-only">Copy token</span>
                </Button>
            </div>
        </div>
    );
}

function RevokeToken({ token }: { token: ApiToken }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    className="text-red-600 hover:text-red-600 dark:text-red-400 dark:hover:text-red-400"
                    data-test={`revoke-token-${token.id}-button`}
                >
                    Revoke
                </Button>
            </DialogTrigger>

            <DialogContent>
                <DialogTitle>Revoke {token.name}?</DialogTitle>
                <DialogDescription>
                    Anything still authenticating with this token starts getting
                    401 responses straight away. This cannot be undone, so a
                    replacement has to be issued and deployed instead.
                </DialogDescription>

                <Form
                    {...ApiTokenController.destroy.form(token.id)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary" type="button">
                                    Cancel
                                </Button>
                            </DialogClose>

                            <Button
                                variant="destructive"
                                disabled={processing}
                                asChild
                            >
                                <button
                                    type="submit"
                                    data-test={`confirm-revoke-token-${token.id}-button`}
                                >
                                    Revoke token
                                </button>
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function Tokens({ tokens, createdToken }: Props) {
    return (
        <>
            <Head title="API tokens" />

            <h1 className="sr-only">API token settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="API tokens"
                    description="Issue a token so the rotator dashboard can reach this application's API"
                />

                {createdToken && <CreatedToken token={createdToken} />}

                <Form
                    {...ApiTokenController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Token name</Label>

                                <Input
                                    id="name"
                                    name="name"
                                    className="mt-1 block w-full"
                                    autoComplete="off"
                                    placeholder="Onyx dashboard"
                                />

                                <InputError message={errors.name} />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="create-token-button"
                                >
                                    Create token
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Active tokens"
                    description="Revoke a token to cut off whatever is still using it"
                />

                {tokens.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        You have not created any API tokens yet.
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Created</TableHead>
                                <TableHead>Last used</TableHead>
                                <TableHead className="text-right">
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            {tokens.map((token) => (
                                <TableRow key={token.id}>
                                    <TableCell className="font-medium">
                                        {token.name}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDate(
                                            token.created_at,
                                            'Unknown',
                                        )}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatDate(
                                            token.last_used_at,
                                            'Never',
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <RevokeToken token={token} />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>
        </>
    );
}

Tokens.layout = {
    breadcrumbs: [
        {
            title: 'API tokens',
            href: edit(),
        },
    ],
};
