import { Check, ChevronDown, Copy, Play } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import ApiResponsePanel from '@/components/api-response-panel';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useClipboard } from '@/hooks/use-clipboard';
import {
    buildBody,
    buildCurl,
    buildPath,
    formatResponseBody,
} from '@/lib/api-request';
import { cn } from '@/lib/utils';
import type {
    ApiConsoleResult,
    ApiEndpoint,
    ApiParameter,
    ApiParameterValues,
} from '@/types';

const methodTones: Record<string, string> = {
    GET: 'text-sky-700 dark:text-sky-300',
    POST: 'text-emerald-700 dark:text-emerald-300',
    PATCH: 'text-amber-700 dark:text-amber-300',
};

const sectionLabels: Record<ApiParameter['in'], string> = {
    path: 'Path parameters',
    query: 'Query parameters',
    body: 'Body',
};

/**
 * One input, rendered as a toggle group when the parameter is an enum.
 *
 * The toggle group is deliberately deselectable: clicking the selected value
 * again clears it, which is how an optional field goes back to being left out
 * of the request entirely. A dropdown gives no way back to unset.
 */
function ParameterField({
    endpointId,
    parameter,
    value,
    onChange,
}: {
    endpointId: string;
    parameter: ApiParameter;
    value: string;
    onChange: (value: string) => void;
}) {
    const id = `${endpointId}-${parameter.name}`;

    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id} className="font-mono text-xs">
                {parameter.name}

                <span className="font-sans text-muted-foreground">
                    {parameter.type}
                    {parameter.required ? ' · required' : ' · optional'}
                </span>
            </Label>

            {parameter.options.length > 0 ? (
                <ToggleGroup
                    id={id}
                    type="single"
                    variant="outline"
                    size="sm"
                    value={value}
                    onValueChange={onChange}
                    className="flex-wrap justify-start"
                >
                    {parameter.options.map((option) => (
                        <ToggleGroupItem
                            key={option}
                            value={option}
                            className="font-mono text-xs"
                        >
                            {option}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
            ) : (
                <Input
                    id={id}
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    placeholder={parameter.example ?? ''}
                    autoComplete="off"
                    spellCheck={false}
                    className="font-mono text-xs"
                    data-test={`parameter-${id}`}
                />
            )}

            <p className="text-xs text-muted-foreground">
                {parameter.description}
            </p>
        </div>
    );
}

/**
 * A documented endpoint, with the console that calls it.
 *
 * The curl snippet and the request the button sends are built from the same
 * two functions, so what you copy is what you just ran.
 */
export default function ApiEndpointCard({
    endpoint,
    baseUrl,
    token,
}: {
    endpoint: ApiEndpoint;
    baseUrl: string;
    token: string;
}) {
    const [values, setValues] = useState<ApiParameterValues>({});
    const [result, setResult] = useState<ApiConsoleResult | null>(null);
    const [failure, setFailure] = useState<string | null>(null);
    const [sending, setSending] = useState(false);
    const [copiedText, copy] = useClipboard();

    const curl = buildCurl(endpoint, values, baseUrl);

    const missing = endpoint.parameters.filter(
        (parameter) => parameter.required && !values[parameter.name]?.trim(),
    );

    const sections = (['path', 'query', 'body'] as const)
        .map((section) => ({
            section,
            parameters: endpoint.parameters.filter(
                (parameter) => parameter.in === section,
            ),
        }))
        .filter(({ parameters }) => parameters.length > 0);

    const handleCopy = async () => {
        if (await copy(curl)) {
            toast.success('Snippet copied to your clipboard.');
        }
    };

    const send = async () => {
        setSending(true);
        setFailure(null);
        setResult(null);

        const body = buildBody(endpoint, values);
        const startedAt = performance.now();

        try {
            const response = await fetch(buildPath(endpoint, values), {
                method: endpoint.method,
                // The session cookie is deliberately withheld. With it attached
                // the response would only prove you are logged into the
                // dashboard in this tab; without it, a 200 proves the token
                // itself works, which is the whole point of the console.
                credentials: 'omit',
                headers: {
                    Accept: 'application/json',
                    Authorization: `Bearer ${token}`,
                    ...(body === null
                        ? {}
                        : { 'Content-Type': 'application/json' }),
                },
                body: body === null ? undefined : JSON.stringify(body),
            });

            setResult({
                status: response.status,
                statusText: response.statusText,
                durationMs: Math.round(performance.now() - startedAt),
                body: formatResponseBody(await response.text()),
            });
        } catch (error) {
            setFailure(
                error instanceof Error
                    ? error.message
                    : 'The request could not be sent.',
            );
        } finally {
            setSending(false);
        }
    };

    return (
        <Collapsible
            className="rounded-xl border"
            data-test={`endpoint-${endpoint.id}`}
        >
            <CollapsibleTrigger className="group flex w-full items-center gap-3 p-4 text-left">
                <Badge
                    variant="outline"
                    className={cn(
                        'w-16 justify-center font-mono',
                        methodTones[endpoint.method],
                    )}
                >
                    {endpoint.method}
                </Badge>

                <div className="min-w-0 flex-1">
                    <p className="truncate font-mono text-sm">
                        /{endpoint.uri}
                    </p>
                    <p className="truncate text-sm text-muted-foreground">
                        {endpoint.title}
                    </p>
                </div>

                <ChevronDown className="size-4 shrink-0 text-muted-foreground transition-transform group-data-[state=open]:rotate-180" />
            </CollapsibleTrigger>

            <CollapsibleContent className="space-y-6 border-t p-4">
                <p className="text-sm text-muted-foreground">
                    {endpoint.summary}
                </p>

                {sections.map(({ section, parameters }) => (
                    <div key={section} className="space-y-4">
                        <h3 className="text-sm font-medium">
                            {sectionLabels[section]}
                        </h3>

                        <div className="grid gap-4 sm:grid-cols-2">
                            {parameters.map((parameter) => (
                                <ParameterField
                                    key={parameter.name}
                                    endpointId={endpoint.id}
                                    parameter={parameter}
                                    value={values[parameter.name] ?? ''}
                                    onChange={(value) =>
                                        setValues((current) => ({
                                            ...current,
                                            [parameter.name]: value,
                                        }))
                                    }
                                />
                            ))}
                        </div>
                    </div>
                ))}

                <div className="space-y-2">
                    <div className="flex items-center justify-between gap-2">
                        <h3 className="text-sm font-medium">curl</h3>

                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => void handleCopy()}
                            data-test={`copy-curl-${endpoint.id}-button`}
                        >
                            {copiedText === curl ? <Check /> : <Copy />}
                            Copy
                        </Button>
                    </div>

                    <pre className="overflow-x-auto rounded-md border bg-muted/40 p-3 font-mono text-xs leading-relaxed">
                        <code>{curl}</code>
                    </pre>
                </div>

                <div className="space-y-3">
                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            type="button"
                            onClick={() => void send()}
                            disabled={
                                sending ||
                                token.trim() === '' ||
                                missing.length > 0
                            }
                            data-test={`send-${endpoint.id}-button`}
                        >
                            {sending ? <Spinner /> : <Play />}
                            Send request
                        </Button>

                        {token.trim() === '' ? (
                            <p className="text-sm text-muted-foreground">
                                Paste a token above to send this request.
                            </p>
                        ) : missing.length > 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Fill in{' '}
                                <span className="font-mono">
                                    {missing
                                        .map((parameter) => parameter.name)
                                        .join(', ')}
                                </span>{' '}
                                to send this request.
                            </p>
                        ) : null}
                    </div>

                    {failure && (
                        <p className="text-sm text-red-600 dark:text-red-400">
                            {failure}
                        </p>
                    )}

                    {result && <ApiResponsePanel result={result} />}
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}
