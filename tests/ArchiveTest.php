<?php

declare(strict_types=1);

use FullSystem\Install\Recipes\Archive;
use FullSystem\Install\Recipes\InvalidArchive;

/**
 * Builds a zip from [entry name => contents]. Entry names are written
 * verbatim, which is how a hostile archive gets built.
 */
function zipWith(array $entries): string
{
    $path = sys_get_temp_dir().'/fullsystem-zip-'.bin2hex(random_bytes(6)).'.zip';

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE);

    foreach ($entries as $name => $contents) {
        $contents === null ? $zip->addEmptyDir($name) : $zip->addFromString($name, $contents);
    }

    $zip->close();

    register_shutdown_function(static fn () => is_file($path) && unlink($path));

    return $path;
}

it('extracts into the destination and returns the archive root', function () {
    $zip = zipWith([
        'starter-kit-main/schema.json' => '{"name":"fullsystem/starter-kit"}',
        'starter-kit-main/stubs/routes/web.php' => '<?php',
    ]);

    $root = Archive::extract($zip, tempDirectory());

    expect(basename($root))->toBe('starter-kit-main')
        ->and(file_get_contents($root.'/schema.json'))->toBe('{"name":"fullsystem/starter-kit"}')
        ->and(is_file($root.'/stubs/routes/web.php'))->toBeTrue();
});

describe('zip slip', function () {
    it('refuses an entry climbing out of the destination', function () {
        $zip = zipWith([
            'starter-kit-main/schema.json' => '{}',
            '../../../../tmp/fullsystem-pwned' => 'owned',
        ]);

        Archive::extract($zip, tempDirectory());
    })->throws(InvalidArchive::class);

    it('refuses an absolute entry', function () {
        Archive::extract(zipWith(['/tmp/fullsystem-pwned' => 'owned']), tempDirectory());
    })->throws(InvalidArchive::class);

    it('writes nothing at all when one entry is unsafe', function () {
        $into = tempDirectory();
        $zip = zipWith([
            'starter-kit-main/schema.json' => '{}',
            'starter-kit-main/../../escape' => 'owned',
        ]);

        try {
            Archive::extract($zip, $into);
        } catch (InvalidArchive) {
            // the whole archive is rejected, not unpacked halfway
        }

        expect(scandir($into))->toBe(['.', '..']);
    });
});

it('refuses an archive that is not a zip', function () {
    $path = sys_get_temp_dir().'/fullsystem-notazip-'.bin2hex(random_bytes(4));
    file_put_contents($path, 'this is not a zip');

    try {
        Archive::extract($path, tempDirectory());
    } finally {
        unlink($path);
    }
})->throws(InvalidArchive::class);

it('refuses an empty archive', function () {
    Archive::extract(zipWith([]), tempDirectory());
})->throws(InvalidArchive::class);

/**
 * GitHub wraps everything in {repo}-{ref}/, but a recipe served by a registry
 * may not. Both have to work.
 */
it('handles an archive with no wrapping directory', function () {
    $zip = zipWith(['schema.json' => '{"name":"acme/recipe"}']);
    $into = tempDirectory();

    $root = Archive::extract($zip, $into);

    expect($root)->toBe($into)
        ->and(is_file($root.'/schema.json'))->toBeTrue();
});
