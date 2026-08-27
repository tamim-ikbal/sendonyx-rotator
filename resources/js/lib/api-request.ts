import type { ApiEndpoint, ApiParameter, ApiParameterValues } from '@/types';

/**
 * What stands in for the reader's token in a copyable snippet.
 *
 * The real token is never written into the snippet. A curl command is the one
 * thing on this page most likely to be pasted into a chat or a ticket, and a
 * Sanctum token cannot be rotated back out of somewhere it has already been
 * pasted.
 */
export const TOKEN_PLACEHOLDER = '$ROTATOR_API_TOKEN';

/**
 * Read a parameter the reader actually filled in.
 *
 * Blank is absence, not an empty string: it is how an optional field stays out
 * of the request instead of being sent as '' for the validator to reject.
 */
function filled(values: ApiParameterValues, name: string): string | null {
    const value = values[name]?.trim();

    return value ? value : null;
}

/**
 * Coerce an input's string into the JSON type the endpoint documents.
 *
 * The literal `null` is passed through as JSON null, which is the only way to
 * type "clear this field" into a text input. The API tells null from an absent
 * key: absent leaves the stored value alone.
 */
function castBodyValue(parameter: ApiParameter, value: string): unknown {
    if (value === 'null') {
        return null;
    }

    if (parameter.type === 'integer') {
        const parsed = Number(value);

        return Number.isFinite(parsed) ? parsed : value;
    }

    return value;
}

/**
 * Build the request path, with the path parameters substituted and the query
 * string appended.
 *
 * A path parameter left blank keeps its `{placeholder}`, so an incomplete
 * snippet reads as incomplete rather than as a url with a hole in it.
 */
export function buildPath(
    endpoint: ApiEndpoint,
    values: ApiParameterValues,
): string {
    const path = endpoint.uri.replace(
        /\{(\w+)\}/g,
        (placeholder, name: string) => {
            const value = filled(values, name);

            return value === null ? placeholder : encodeURIComponent(value);
        },
    );

    const query = new URLSearchParams();

    for (const parameter of endpoint.parameters) {
        if (parameter.in !== 'query') {
            continue;
        }

        const value = filled(values, parameter.name);

        if (value !== null) {
            query.append(parameter.name, value);
        }
    }

    const queryString = query.toString();

    return `/${path}${queryString ? `?${queryString}` : ''}`;
}

/**
 * Build the JSON body, or null when the request carries none.
 */
export function buildBody(
    endpoint: ApiEndpoint,
    values: ApiParameterValues,
): Record<string, unknown> | null {
    const body: Record<string, unknown> = {};

    for (const parameter of endpoint.parameters) {
        if (parameter.in !== 'body') {
            continue;
        }

        const value = filled(values, parameter.name);

        if (value !== null) {
            body[parameter.name] = castBodyValue(parameter, value);
        }
    }

    return Object.keys(body).length > 0 ? body : null;
}

/**
 * Escape a value for a single quoted shell string.
 */
function shellQuote(value: string): string {
    return value.replace(/'/g, `'\\''`);
}

/**
 * Render the curl command for the request the console would send.
 *
 * Built from the same two functions the console sends with, so the snippet
 * cannot describe a different call from the one the button makes.
 */
export function buildCurl(
    endpoint: ApiEndpoint,
    values: ApiParameterValues,
    baseUrl: string,
): string {
    const method = endpoint.method === 'GET' ? '' : ` -X ${endpoint.method}`;

    const lines = [
        `curl${method} "${baseUrl}${buildPath(endpoint, values)}"`,
        `  -H "Authorization: Bearer ${TOKEN_PLACEHOLDER}"`,
        '  -H "Accept: application/json"',
    ];

    const body = buildBody(endpoint, values);

    if (body !== null) {
        lines.push('  -H "Content-Type: application/json"');
        lines.push(`  -d '${shellQuote(JSON.stringify(body, null, 2))}'`);
    }

    return lines.join(' \\\n');
}

/**
 * Pretty print a response body, leaving anything that is not JSON alone.
 *
 * An error page or an empty 204 both arrive here, and neither should be lost
 * behind a parse failure.
 */
export function formatResponseBody(body: string): string {
    if (body.trim() === '') {
        return '';
    }

    try {
        return JSON.stringify(JSON.parse(body), null, 2);
    } catch {
        return body;
    }
}
