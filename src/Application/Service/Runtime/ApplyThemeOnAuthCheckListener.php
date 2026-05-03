<?php

declare(strict_types=1);

namespace Semitexa\Theme\Application\Service\Runtime;

use Semitexa\Core\Attribute\AsPipelineListener;
use Semitexa\Core\Attribute\InjectAsMutable;
use Semitexa\Core\Pipeline\AuthCheck;
use Semitexa\Core\Pipeline\PipelineListenerInterface;
use Semitexa\Core\Pipeline\RequestPipelineContext;
use Semitexa\Core\Request;
use Semitexa\Locale\Context\LocaleContextStore;
use Semitexa\Tenancy\Context\TenantContextStore;
use Semitexa\Theme\Exception\InvalidThemeConfigException;
use Semitexa\Theme\Exception\ThemeResolutionException;
use Semitexa\Theme\Domain\Model\ThemeContext;

/**
 * Populates ThemeContextStore from the fully-assembled request context.
 *
 * Why a pipeline listener (and not a TenantResolved event listener):
 *
 *   The earlier design hooked TenantResolved and read the host from
 *   $event->context->getSource(). That works for the classic
 *   SubdomainStrategy which stores host via TenantContext::fromResolution.
 *   It does NOT work under the default MultilayerTenantResolver, which
 *   runs OrganizationStrategy + LocaleStrategy + EnvironmentStrategy and
 *   constructs a multi-layer TenantContext without a source — the host
 *   information is discarded at the Organization layer boundary.
 *
 *   Additionally, TenantResolved fires during TenancyPhase, BEFORE
 *   SessionPhase has established the container's ExecutionContext. That
 *   made Request injection via #[InjectAsMutable] throw
 *   ExecutionContextNotReadyException.
 *
 *   A pipeline listener on AuthCheck::class runs AFTER SessionPhase and
 *   LocalePhase, so execution context is ready (Request injectable) and
 *   both TenantContextStore and LocaleContextStore are populated. This
 *   is the canonical join point for the three context axes.
 *
 * Priority=1 so we run before any authorization/authentication listeners
 * that may care about the theme (none today, but the ordering matters
 * once a SkinedLoginPage listener is added later).
 *
 * Resilience: failures log + swallow. ThemeTwigExtension's lazy fallback
 * re-runs resolution on first template call, so a listener failure does
 * not 500 the page.
 */
#[AsPipelineListener(phase: AuthCheck::class, priority: 1)]
final class ApplyThemeOnAuthCheckListener implements PipelineListenerInterface
{
    #[InjectAsMutable]
    protected Request $request;

    public function handle(RequestPipelineContext $context): void
    {
        try {
            $themeCtx = new ThemeContext(
                tenant: $this->currentTenantId(),
                domain: $this->currentDomain(),
                locale: LocaleContextStore::getLocale(),
            );
            $assignment = ThemeResolverBootstrap::resolver()->resolve($themeCtx);
            ThemeContextStore::set($assignment);
        } catch (InvalidThemeConfigException | ThemeResolutionException $e) {
            error_log('[semitexa/theme] resolver failed: ' . $e->getMessage());
        } catch (\Throwable $e) {
            error_log('[semitexa/theme] unexpected error in pipeline listener: ' . $e::class . ': ' . $e->getMessage());
        }
    }

    private function currentTenantId(): ?string
    {
        if (! class_exists(TenantContextStore::class, false)) {
            return null;
        }
        $ctx = TenantContextStore::shared()->tryGet();
        if ($ctx === null || ! method_exists($ctx, 'getTenantId')) {
            return null;
        }
        $id = (string) $ctx->getTenantId();
        return $id === '' || $id === 'default' ? null : $id;
    }

    private function currentDomain(): string
    {
        $host = strtolower(trim($this->request->getHost()));
        if ($host !== '' && str_contains($host, ':')) {
            $host = substr($host, 0, strpos($host, ':'));
        }
        return $host;
    }
}
