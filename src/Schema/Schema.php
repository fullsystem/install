<?php

declare(strict_types=1);

namespace FullSystem\Install\Schema;

use JsonException;

/**
 * What a theme declares about itself.
 *
 * Only the identity is read so far. The phases are kept raw until the actions
 * that consume them exist — parsing them now would mean guessing the shape
 * before anything can validate it.
 */
final readonly class Schema
{
    public const string FILE = 'schema.json';

    /** Where the theme keeps the files that mirror the project root. */
    public const string DEFAULT_SOURCE = 'stubs';

    /**
     * @param  array<mixed>  $phases
     * @param  list<string>  $requires  names of checks this theme depends on
     */
    public function __construct(
        public ?string $name,
        public ?string $version,
        public array $phases,
        public string $source = self::DEFAULT_SOURCE,
        public ?string $driver = null,
        public array $requires = [],
    ) {}

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw new InvalidSchema('The theme has no '.self::FILE.'.');
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new InvalidSchema('Could not read '.self::FILE.'.');
        }

        return self::fromJson($contents);
    }

    /**
     * @param  array<mixed>  $data
     */
    private static function text(array $data, string $key): ?string
    {
        return isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '' ? $data[$key] : null;
    }

    /**
     * @param  array<mixed>  $data
     * @return list<string>
     */
    private static function names(array $data, string $key): array
    {
        if (! isset($data[$key]) || ! is_array($data[$key])) {
            return [];
        }

        return array_values(array_filter($data[$key], is_string(...)));
    }

    public static function fromJson(string $json): self
    {
        try {
            /** @var mixed $data */
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidSchema('Invalid '.self::FILE.': '.$exception->getMessage());
        }

        if (! is_array($data)) {
            throw new InvalidSchema('Invalid '.self::FILE.': expected an object.');
        }

        return new self(
            name: self::text($data, 'name'),
            version: self::text($data, 'version'),
            phases: isset($data['phases']) && is_array($data['phases']) ? $data['phases'] : [],
            source: self::text($data, 'source') ?? self::DEFAULT_SOURCE,
            driver: self::text($data, 'driver'),
            requires: self::names($data, 'requires'),
        );
    }
}
