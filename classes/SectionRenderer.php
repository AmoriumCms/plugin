<?php namespace AmoriumCms\Amorium\Classes;

use Log;
use Config;
use Cms\Classes\Theme;
use Cms\Classes\Controller;
use Twig\Error\Error as TwigError;

/**
 * SectionRenderer renders the HTML of a section record as a Twig template
 * with the field values entered for it on the page available as "data".
 *
 * The spacing chosen on the page is available as "section.spacing", which
 * falls back to DEFAULT_SPACING, and as "section.custom_spacing", which is
 * empty when nothing was chosen, for sections with their own spacing.
 * The choices are listed under sectionSpacings in theme.yaml as classes => label.
 */
class SectionRenderer
{
    public const DEFAULT_SPACING = 'md:py-24 py-16';

    public static function render($section, $data = null, $index = null, $spacing = null): string
    {
        $html = (string) $section?->html;
        if (trim($html) === '') {
            return '';
        }

        $controller = Controller::getController();
        if (!$controller) {
            return $html;
        }

        $customSpacing = static::spacing($spacing);

        $vars = [
            'section' => [
                'id' => $section->id,
                'title' => $section->title,
                'slug' => $section->slug,
                'index' => (int) $index,
                'spacing' => $customSpacing ?: static::DEFAULT_SPACING,
                'custom_spacing' => $customSpacing,
            ],
            'data' => is_array($data) ? $data : [],
        ];

        try {
            return $controller->getTwig()
                ->createTemplate($html, 'amorium-section-' . $section->id)
                ->render($vars);
        }
        catch (TwigError $ex) {
            if (Config::get('app.debug')) {
                throw $ex;
            }

            Log::error($ex);
            return '';
        }
    }

    /**
     * spacingOptions returns the spacing choices of the active theme for the page section form
     */
    public static function spacingOptions(): array
    {
        $options = [];

        foreach ((array) Theme::getActiveTheme()?->getConfigValue('sectionSpacings') as $classes => $label) {
            if ($classes = static::spacing($classes)) {
                $options[$classes] = __((string) $label);
            }
        }

        return $options;
    }

    /**
     * spacing keeps the class names of a spacing, so it cannot break out of the class attribute
     */
    public static function spacing($value): string
    {
        $classes = preg_split('/\s+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);
        $classes = array_filter($classes, fn ($class) => preg_match('/^[a-zA-Z0-9:\-\/\[\]\.\%#_!]+$/', $class));

        return implode(' ', $classes);
    }
}
