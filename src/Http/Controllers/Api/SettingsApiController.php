<?php

namespace Backpack\Settings\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsApiController extends Controller
{
    /**
     * GET /api/settings
     * Возвращает все настройки из БД в формате { "key": value }
     */
    public function db(Request $request)
    {
        $table = config('backpack-settings.table', 'backpack_settings');

        $q = DB::table($table)->select(['key','value','cast','group','updated_at']);

        if ($group = $request->query('group')) {
            $q->where('group', $group);
        }
        if ($prefix = $request->query('prefix')) {
            $q->where('key', 'like', $prefix.'%');
        }

        $rows = $q->get();

        $data = [];
        foreach ($rows as $row) {
            $data[$row->key] = $this->castOut($row->value, $row->cast);
        }

        return response()->json($data);
    }

    protected function castOut($value, ?string $cast)
    {
        if ($cast === null) return $value;

        switch ($cast) {
            case 'bool':
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'json':
            case 'array':
                $decoded = json_decode($value, true);
                return $decoded === null ? [] : $decoded;
            case 'string':
                return (string) $value;
            default:
                return $value;
        }
    }
}
