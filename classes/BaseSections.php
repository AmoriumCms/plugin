<?php namespace AmoriumCms\Amorium\Classes;

use File;
use Event;
use Tailor\Models\EntryRecord;

/**
 * BaseSections keeps the default sections in a JSON file so they can be
 * created again on a fresh install or after they were deleted.
 *
 * Other plugins add their own sections by returning the path of a JSON file
 * in the same format from the amorium.sections.basePaths event. Those
 * sections are written back to the file of their plugin on export.
 */
class BaseSections
{
    public const HANDLE = 'Amorium\Section';

    public const ATTRIBUTES = ['title', 'slug', 'html', 'fields_definition', 'css', 'js'];

    public const EVENT = 'amorium.sections.basePaths';

    /**
     * path of the JSON file
     */
    public static function path(): string
    {
        return plugins_path('amoriumcms/amorium/data/base-sections.json');
    }

    /**
     * extraPaths returns the JSON files added by other plugins
     */
    public static function extraPaths(): array
    {
        $paths = [];

        foreach ((array) Event::fire(static::EVENT) as $result) {
            foreach ((array) $result as $path) {
                if (is_string($path) && $path !== '' && $path !== static::path()) {
                    $paths[] = $path;
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * export writes the current sections to the JSON files and returns their count.
     * A section listed in the file of another plugin is updated in that file, the
     * sections of that file missing on this site are kept.
     */
    public static function export(): int
    {
        $sections = EntryRecord::inSection(static::HANDLE)
            ->orderBy('id')
            ->get()
            ->map(fn ($record) => array_map(
                fn ($value) => str_replace("\r\n", "\n", (string) $value),
                $record->only(static::ATTRIBUTES)
            ))
            ->keyBy('slug');

        $count = 0;

        foreach (static::extraPaths() as $path) {
            $owned = static::read($path);
            if (!$owned) {
                continue;
            }

            foreach ($owned as $index => $data) {
                if ($section = $sections->pull($data['slug'])) {
                    $owned[$index] = $section;
                    $count++;
                }
            }

            static::write($path, $owned);
        }

        static::write(static::path(), $sections->values()->all());

        return $count + $sections->count();
    }

    /**
     * create adds the sections from the JSON files whose slug is not in use
     * and returns the titles of the created and skipped sections.
     */
    public static function create(): array
    {
        $result = ['created' => [], 'skipped' => []];

        foreach (static::load() as $data) {
            $record = EntryRecord::inSection(static::HANDLE)->where('slug', $data['slug'])->withTrashed()->first();

            if ($record && !$record->trashed()) {
                $result['skipped'][] = $record->title;
                continue;
            }

            if ($record) {
                $record->restore();
            }
            else {
                $record = EntryRecord::inSection(static::HANDLE);
            }

            foreach (static::ATTRIBUTES as $attribute) {
                $record->{$attribute} = $data[$attribute] ?? '';
            }

            $record->is_enabled = true;
            $record->save();

            $result['created'][] = $record->title;
        }

        return $result;
    }

    /**
     * load returns the sections of every JSON file, the first file using a slug wins
     */
    protected static function load(): array
    {
        $sections = [];

        foreach (array_merge([static::path()], static::extraPaths()) as $path) {
            foreach (static::read($path) as $data) {
                $sections[$data['slug']] ??= $data;
            }
        }

        return array_values($sections);
    }

    /**
     * read
     */
    protected static function read(string $path): array
    {
        if (!File::exists($path)) {
            return [];
        }

        $sections = json_decode(File::get($path), true);

        return array_values(array_filter(
            is_array($sections) ? $sections : [],
            fn ($data) => is_array($data) && !empty($data['slug']) && !empty($data['title'])
        ));
    }

    /**
     * write
     */
    protected static function write(string $path, array $sections): void
    {
        File::makeDirectory(dirname($path), 0755, true, true);
        File::put($path, json_encode(array_values($sections), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    }
}
