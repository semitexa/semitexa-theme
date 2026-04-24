<?php

declare(strict_types=1);

namespace Semitexa\Theme\Exception;

/**
 * Raised when var/config/themes.json (or any repository source) fails
 * structural validation: missing default, dangling theme/skin reference,
 * unknown locale (without --allow-unknown-locales), malformed JSON.
 *
 * Fatal at boot — the application refuses to start on invalid config.
 */
class InvalidThemeConfigException extends \RuntimeException
{
}
