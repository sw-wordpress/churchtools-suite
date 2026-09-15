<?php

/**
 * Shortcode Handler
 * 
 * Handles all frontend shortcodes for displaying events in various views.
 * Supports all view types from ROADMAP v0.5.0.0
 *
 * @package ChurchTools_Suite
 * @since   0.5.0.0
 */

if (! defined('ABSPATH')) {
    exit;
}

class ChurchTools_Suite_Styles
{
    private const BLACK = '#000000';
    private const WHITE = '#FFFFFF';

    /**
     * Luminance at which black and white have the same WCAG contrast ratio.
     *
     * @var float
     */
    private const CONTRAST_LUMINANCE_THRESHOLD = 0.2791287847;

    /**
     * Return whichever of black or white has the higher contrast against a color.
     *
     * Accepts three- or six-digit hexadecimal colors, with an optional leading
     * hash. Surrounding whitespace is ignored.
     *
     * @param string $hex Hexadecimal color, for example "#1a2b3c" or "abc".
     *
     * @return string "#000000" or "#FFFFFF".
     * @throws InvalidArgumentException When the supplied color is invalid.
     */
    public static function contrast_color(string $hex): string
    {
        $color = trim($hex);

        // Remove one optional prefix; multiple hashes remain invalid.
        if (isset($color[0]) && '#' === $color[0]) {
            $color = substr($color, 1);
        }

        if (1 !== preg_match('/\A(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})\z/', $color)) {
            throw new InvalidArgumentException(
                sprintf('Invalid hexadecimal color "%s"; expected RGB or RRGGBB.', $hex)
            );
        }

        if (3 === strlen($color)) {
            $color = $color[0] . $color[0]
                . $color[1] . $color[1]
                . $color[2] . $color[2];
        }

        // Parse once and extract the three 8-bit sRGB channels.
        $rgb = hexdec($color);
        $red = self::linearize_srgb_channel(($rgb >> 16) & 0xFF);
        $green = self::linearize_srgb_channel(($rgb >> 8) & 0xFF);
        $blue = self::linearize_srgb_channel($rgb & 0xFF);

        $luminance = (0.2126 * $red)
            + (0.7152 * $green)
            + (0.0722 * $blue);

        return $luminance > self::CONTRAST_LUMINANCE_THRESHOLD
            ? self::BLACK
            : self::WHITE;
    }

    /**
     * Convert an 8-bit sRGB channel to its linear-light value.
     */
    private static function linearize_srgb_channel(int $channel): float
    {
        $normalized = $channel / 255;

        return $normalized <= 0.04045
            ? $normalized / 12.92
            : (($normalized + 0.055) / 1.055) ** 2.4;
    }
}
