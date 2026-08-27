export type ApiParameterLocation = 'path' | 'query' | 'body';

export type ApiParameter = {
    name: string;
    in: ApiParameterLocation;
    type: string;
    required: boolean;
    description: string;
    example: string | null;
    options: string[];
};

export type ApiEndpoint = {
    id: string;
    method: string;
    uri: string;
    title: string;
    summary: string;
    parameters: ApiParameter[];
};

export type ApiEndpointGroup = {
    name: string;
    description: string;
    endpoints: ApiEndpoint[];
};

/**
 * What the reader typed into an endpoint's parameter fields, keyed by name.
 *
 * Every value is the raw string out of the input. Blank means "do not send
 * this", which is how an optional field stays absent rather than being sent
 * as an empty string the validator would reject.
 */
export type ApiParameterValues = Record<string, string>;

export type ApiConsoleResult = {
    status: number;
    statusText: string;
    durationMs: number;
    body: string;
};
