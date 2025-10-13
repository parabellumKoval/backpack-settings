<?php

namespace Backpack\Settings\Services;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;

class SettingsContextResolver
{
    protected Request $request;
    protected ConfigRepository $config;

    public function __construct(Request $request, ConfigRepository $config)
    {
        $this->request = $request;
        $this->config = $config;
    }

    /**
     * @param array<string,mixed>|object|null $context
     * @return array<string,mixed>
     */
    public function resolve($context = []): array
    {
        $context = $this->normalizeContext($context);

        if (!array_key_exists('region', $context)) {
            $context['region'] = $this->resolveRegion();
        } else {
            $context['region'] = $this->normalizeScalar($context['region']);
        }

        $context['locale'] = $this->normalizeLocale($context['locale'] ?? null) ?? $this->resolveLocale();
        $context['fallback_locale'] = $this->normalizeLocale($context['fallback_locale'] ?? null) ?? $this->resolveFallbackLocale($context['locale']);

        return $context;
    }

    /**
     * @param array<string,mixed>|object|null $context
     * @return array<string,mixed>
     */
    protected function normalizeContext($context): array
    {
        if ($context instanceof Arrayable) {
            return $context->toArray();
        }

        if (is_object($context)) {
            if (method_exists($context, 'toArray')) {
                return (array) $context->toArray();
            }

            if (method_exists($context, 'jsonSerialize')) {
                $serialized = $context->jsonSerialize();
                if (is_array($serialized)) {
                    return $serialized;
                }
            }

            return get_object_vars($context);
        }

        if (is_array($context)) {
            return $context;
        }

        return [];
    }

    protected function resolveRegion(): ?string
    {
        $config = $this->config->get('backpack-settings.context', []);
        $queryParam = $config['region_query_parameter'] ?? 'region';
        $header = $config['region_header'] ?? null;
        $sessionKey = $config['region_session_key'] ?? null;

        if ($queryParam && $this->request->query($queryParam) !== null) {
            return $this->normalizeScalar($this->request->query($queryParam));
        }

        if ($header && $this->request->headers->has($header)) {
            return $this->normalizeScalar($this->request->headers->get($header));
        }

        if ($sessionKey && $this->request->hasSession() && $this->request->session()->has($sessionKey)) {
            return $this->normalizeScalar($this->request->session()->get($sessionKey));
        }

        return $this->normalizeScalar($config['default_region'] ?? null);
    }

    protected function resolveLocale(): string
    {
        $context = $this->config->get('backpack-settings.context', []);
        $supported = $context['supported_locales'] ?? [];

        $locale = $this->normalizeLocale(app()->getLocale());
        if ($locale !== null) {
            return $locale;
        }

        if (!empty($supported)) {
            $preferred = $this->request->getPreferredLanguage($supported);
            if ($preferred) {
                return $preferred;
            }
        } else {
            $preferred = $this->request->getPreferredLanguage();
            if ($preferred) {
                return $preferred;
            }
        }

        return $this->normalizeLocale($this->config->get('app.locale')) ?? 'en';
    }

    protected function resolveFallbackLocale(?string $locale): ?string
    {
        $fallback = $this->normalizeLocale($this->config->get('app.fallback_locale'));
        if ($fallback !== null) {
            return $fallback;
        }

        return $locale ?? $this->normalizeLocale($this->config->get('app.locale'));
    }

    protected function normalizeLocale($value): ?string
    {
        $value = $this->normalizeScalar($value);

        return $value === null ? null : str_replace('_', '-', $value);
    }

    /**
     * @param mixed $value
     */
    protected function normalizeScalar($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed === '' ? null : $trimmed;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return null;
    }
}
