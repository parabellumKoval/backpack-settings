<?php

namespace Backpack\Settings\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Backpack\Settings\Facades\Settings;
use Illuminate\Http\Request;
use Backpack\Settings\Facades\SettingsRegistry;

class SettingsController extends Controller
{
    public function edit(string $groupSlug)
    {
        $registry = SettingsRegistry::getFacadeRoot();
        $group = $registry->get($groupSlug);
        abort_if(!$group, 404);

        // Build pages fields
        $pages = [];
        foreach ($group->pages as $page) {
            $fields = [];
            foreach ($page->fields as $f) {
                $current = Settings::get($f->key, $f->default);
                $fields[] = $f->toBackpackArray($current);
            }
            $pages[] = [
                'title' => $page->title,
                'fields' => $fields,
            ];
        }

        return view(config('backpack-settings.view_namespace').'::settings.edit', [
            'group' => $group,
            'pages' => $pages,
            'action' => route('backpack.settings.update', ['group' => $groupSlug]),
        ]);
    }

    public function update(Request $request, string $groupSlug)
    {
        $registry = SettingsRegistry::getFacadeRoot();
        $group = $registry->get($groupSlug);
        abort_if(!$group, 404);

        $payload = $request->input('settings', []);

        // dd($payload);
        foreach ($group->pages as $page) {
            foreach ($page->fields as $f) {
                $key = $f->key;
                if (array_key_exists($key, $payload)) {
                    $value = $payload[$key];
                    // Normalize checkbox
                    if ($f->type === 'checkbox') {
                        $value = $value ? '1' : '0';
                    }
                    Settings::set($key, $value, ['cast' => $f->cast, 'group' => $groupSlug]);
                } else {
                    // For unchecked checkbox, persist false
                    if ($f->type === 'checkbox') {
                        Settings::set($key, '0', ['cast' => $f->cast, 'group' => $groupSlug]);
                    }
                }
            }
        }

        return redirect()->back()->with('success', 'Settings saved.');
    }
}
