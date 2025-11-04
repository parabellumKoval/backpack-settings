<?php

namespace Backpack\Settings\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Backpack\Settings\Facades\Settings;

class SettingsApiController extends Controller
{
    /**
     * GET /api/settings
     * Возвращает все настройки из БД в формате { "key": value }
     */
    public function db(Request $request)
    {
        $table = config('backpack-settings.table', 'backpack_settings');

        $q = DB::table($table)->select(['key'])->distinct();

        if ($group = $request->query('group')) {
            $q->where('group', $group);
        }
        if ($prefix = $request->query('prefix')) {
            $q->where('key', 'like', $prefix.'%');
        }

        $keys = $q->pluck('key')->unique()->toArray();
        $context = $this->resolveContext($request);
        $data = [];
        foreach ($keys as $key) {
            $data[$key] = Settings::get($key, null, $context);
        }

        return response()->json($data);
    }

    /**
     * GET /api/settings/nested
     * Returns settings as a nested object structure based on dot notation
     */
    public function nested(Request $request)
    {
        $table = config('backpack-settings.table', 'backpack_settings');

        $q = DB::table($table)->select(['key'])->distinct();

        if ($group = $request->query('group')) {
            $q->where('group', $group);
        }
        if ($prefix = $request->query('prefix')) {
            $q->where('key', 'like', $prefix.'%');
        }

        $keys = $q->pluck('key')->unique()->toArray();
        $result = [];
        $context = $this->resolveContext($request);

        foreach ($keys as $key) {
            $segments = explode('.', $key);
            $value = Settings::get($key, null, $context);
            $this->arraySet($result, $segments, $value);
        }

        return response()->json($result);
    }

    /**
     * Helper method to set nested array values
     */
    protected function arraySet(&$array, $keys, $value)
    {
        $current = &$array;
        
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $current[$key] = $value;
            } else {
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
        }
    }

    protected function resolveContext(Request $request): array
    {
        $localeParam = config('backpack-settings.locale_query_parameter', 'locale');
        $regionParam = config('backpack-settings.region_query_parameter', 'country');

        $context = [
            'locale' => $this->normalizeLocale($request->query($localeParam)),
            'region' => $this->normalizeRegion($request->query($regionParam)),
        ];

        $acceptLanguage = $request->header('Accept-Language');
        if ($acceptLanguage) {
            $context['accept_language'] = $acceptLanguage;
        }

        return $context;
    }

    protected function normalizeLocale($locale): ?string
    {
        if ($locale === null || $locale === '') {
            return null;
        }
        return str_replace('_', '-', strtolower((string) $locale));
    }

    protected function normalizeRegion($region): ?string
    {
        if ($region === null || $region === '') {
            return null;
        }
        return strtolower((string) $region);
    }
}
