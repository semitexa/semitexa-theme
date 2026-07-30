<?php

declare(strict_types=1);

namespace Semitexa\Theme;

use Semitexa\Core\Attribute\Capability;

/**
 * What this package offers, for the capability catalog.
 *
 * Without this the package is invisible to anyone whose project has not
 * installed it - which is precisely the audience worth telling, since they are
 * the ones about to build it by hand. The convention is one `Capabilities` class
 * per package: a definite place to look, and a definite place for a guard to
 * check.
 *
 * Nothing reads this at runtime.
 */
#[Capability(
    id: 'theme.skins',
    summary: 'A theme and skin resolver with layouts, partials and token-driven skins over one shared markup set.',
    useWhen: 'The same application has to look different per site, tenant or mode without forking its templates.',
    avoidWhen: 'One appearance, one stylesheet, no plan for a second.',
    replaces: [
        'a forked copy of every template to change colours',
        'a stylesheet per tenant, hand-kept in sync with the markup',
    ],
)]
final class Capabilities
{
}
