import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { ApiConsoleResult } from '@/types';

/**
 * Colour the status by its class, so a 422 does not read like a 200.
 */
function statusTone(status: number): string {
    if (status >= 500) {
        return 'bg-red-500';
    }

    if (status >= 400) {
        return 'bg-amber-500';
    }

    if (status >= 200 && status < 300) {
        return 'bg-emerald-500';
    }

    return 'bg-muted-foreground';
}

/**
 * The response to the last request the console sent.
 */
export default function ApiResponsePanel({
    result,
}: {
    result: ApiConsoleResult;
}) {
    return (
        <div className="space-y-2" data-test="api-console-response">
            <div className="flex flex-wrap items-center gap-2">
                <Badge variant="secondary">
                    <span
                        className={cn(
                            'size-1.5 rounded-full',
                            statusTone(result.status),
                        )}
                    />
                    {result.status}
                    {/* HTTP/2 carries no reason phrase, so this is often blank. */}
                    {result.statusText ? ` ${result.statusText}` : ''}
                </Badge>

                <span className="text-xs text-muted-foreground tabular-nums">
                    {result.durationMs} ms
                </span>
            </div>

            <pre className="max-h-96 overflow-auto rounded-md border bg-muted/40 p-3 font-mono text-xs leading-relaxed">
                <code>{result.body || 'No response body.'}</code>
            </pre>
        </div>
    );
}
