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

    /**
     * @param  array<mixed>  $phases
     */
    public function __construct(
        public ?string $name,
        public ?string $version,
        public array $phases,
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
            name: isset($data['name']) && is_string($data['name']) ? $data['name'] : null,
            version: isset($data['version']) && is_string($data['version']) ? $data['version'] : null,
            phases: isset($data['phases']) && is_array($data['phases']) ? $data['phases'] : [],
        );
    }
}
