<?php

namespace App\Support\ApiDocs;

/**
 * One documented input to an API endpoint.
 *
 * The docs page renders a form control per parameter and builds both the curl
 * snippet and the live request from what the reader types into them, so the
 * snippet and the request can never describe different calls.
 */
final readonly class ApiParameter
{
    public const IN_PATH = 'path';

    public const IN_QUERY = 'query';

    public const IN_BODY = 'body';

    /**
     * @param  array<int, string>  $options  The accepted values, when the parameter is an enum.
     */
    private function __construct(
        public string $name,
        public string $in,
        public string $type,
        public bool $required,
        public string $description,
        public ?string $example,
        public array $options,
    ) {}

    /**
     * Define a segment of the url. Path parameters are always required.
     */
    public static function path(string $name, string $description, ?string $example = null): self
    {
        return new self($name, self::IN_PATH, 'string', true, $description, $example, []);
    }

    /**
     * Define a query string parameter.
     *
     * @param  array<int, string>  $options
     */
    public static function query(
        string $name,
        string $type,
        string $description,
        ?string $example = null,
        array $options = [],
    ): self {
        return new self($name, self::IN_QUERY, $type, false, $description, $example, $options);
    }

    /**
     * Define a JSON body field.
     *
     * @param  array<int, string>  $options
     */
    public static function body(
        string $name,
        string $type,
        bool $required,
        string $description,
        ?string $example = null,
        array $options = [],
    ): self {
        return new self($name, self::IN_BODY, $type, $required, $description, $example, $options);
    }

    /**
     * Reduce the parameter to the props the docs page renders.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'in' => $this->in,
            'type' => $this->type,
            'required' => $this->required,
            'description' => $this->description,
            'example' => $this->example,
            'options' => $this->options,
        ];
    }
}
