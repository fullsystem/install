<?php

declare(strict_types=1);

namespace FullSystem\Install\Themes;

use Stringable;

/**
 * A theme is `owner/repository` and nothing else.
 *
 * The value goes straight into a URL, so anything that could steer the
 * request somewhere other than the intended repository is refused here,
 * before a request exists.
 */
final readonly class Theme implements Stringable
{
    private const string PATTERN = '/^[a-z0-9][\w.-]*\/[a-z0-9][\w.-]*$/i';

    private const string DEFAULT_REF = 'main';

    private function __construct(
        public string $owner,
        public string $repository,
    ) {}

    public static function fromString(string $name): self
    {
        if (preg_match(self::PATTERN, $name) !== 1) {
            throw new InvalidTheme("A theme is owner/repository — got: {$name}");
        }

        [$owner, $repository] = explode('/', $name);

        return new self($owner, $repository);
    }

    public function archiveUrl(string $ref = self::DEFAULT_REF): string
    {
        return "https://github.com/{$this->owner}/{$this->repository}/archive/refs/heads/{$ref}.zip";
    }

    public function __toString(): string
    {
        return "{$this->owner}/{$this->repository}";
    }
}
