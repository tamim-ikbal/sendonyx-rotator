import { Head, Link } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { useState } from 'react';
import ApiEndpointCard from '@/components/api-endpoint-card';
import Heading from '@/components/heading';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { TOKEN_PLACEHOLDER } from '@/lib/api-request';
import { edit as tokenSettings } from '@/routes/api-tokens';
import { index } from '@/routes/docs';
import type { ApiEndpointGroup } from '@/types';

export default function ApiDocs({
    baseUrl,
    groups,
}: {
    baseUrl: string;
    groups: ApiEndpointGroup[];
}) {
    // Held in this component and nowhere else. Sanctum stores only a hash, so
    // the application genuinely cannot fill this in for you, and keeping it out
    // of local storage means closing the tab is enough to be rid of it.
    const [token, setToken] = useState('');

    return (
        <>
            <Head title="API docs" />

            <div className="flex h-full flex-1 flex-col gap-8 p-4">
                <Heading
                    title="API docs"
                    description="Every endpoint the rotator API exposes, with a console for calling it"
                />

                <section className="space-y-4 rounded-xl border p-4">
                    <div className="space-y-1">
                        <h2 className="flex items-center gap-2 font-medium">
                            <KeyRound className="size-4" />
                            Authentication
                        </h2>

                        <p className="text-sm text-muted-foreground">
                            Every request needs a Sanctum token in an{' '}
                            <code className="font-mono">Authorization</code>{' '}
                            header, and answers 401 without one. Send{' '}
                            <code className="font-mono">
                                Accept: application/json
                            </code>{' '}
                            as well, or a validation failure comes back as a
                            redirect instead of a 422.
                        </p>
                    </div>

                    <pre className="overflow-x-auto rounded-md border bg-muted/40 p-3 font-mono text-xs">
                        <code>
                            Authorization: Bearer {TOKEN_PLACEHOLDER}
                            {'\n'}Accept: application/json
                        </code>
                    </pre>

                    <div className="grid gap-2">
                        <Label htmlFor="token">Token</Label>

                        <Input
                            id="token"
                            type="password"
                            value={token}
                            onChange={(event) => setToken(event.target.value)}
                            placeholder="1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                            autoComplete="off"
                            spellCheck={false}
                            className="font-mono"
                            data-test="api-token-input"
                        />

                        <p className="text-sm text-muted-foreground">
                            Used only by the Send request buttons below, and
                            never written into a snippet or stored anywhere.
                            Reloading the page clears it.{' '}
                            <Link
                                href={tokenSettings()}
                                className="underline underline-offset-4"
                                data-test="issue-token-link"
                            >
                                Issue a token
                            </Link>
                            .
                        </p>
                    </div>
                </section>

                {groups.map((group) => (
                    <section key={group.name} className="space-y-4">
                        <Heading
                            variant="small"
                            title={group.name}
                            description={group.description}
                        />

                        <div className="space-y-3">
                            {group.endpoints.map((endpoint) => (
                                <ApiEndpointCard
                                    key={endpoint.id}
                                    endpoint={endpoint}
                                    baseUrl={baseUrl}
                                    token={token}
                                />
                            ))}
                        </div>
                    </section>
                ))}
            </div>
        </>
    );
}

ApiDocs.layout = {
    breadcrumbs: [
        {
            title: 'API docs',
            href: index(),
        },
    ],
};
