<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MessageTemplate;
use App\Services\ActivityLogger;
use App\Support\MessageTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Message Templates (admins only): the wording of the messages sent to clients, with their
 * placeholders. Saving keeps the admin's wording; Reset goes back to the default.
 */
class MessageTemplateController extends Controller
{
    public function index(): View
    {
        $custom = MessageTemplate::with('editor')->get()->keyBy('key');

        return view('admin.message-templates.index', [
            'templates' => collect(MessageTemplates::TEMPLATES)->map(fn (array $template, string $key) => $template + [
                'key' => $key,
                'custom' => $custom->get($key),
            ]),
        ]);
    }

    public function edit(string $key): View
    {
        return view('admin.message-templates.edit', [
            'key' => $key,
            'template' => MessageTemplates::TEMPLATES[$key],
            'current' => MessageTemplates::get($key),
            'samples' => MessageTemplates::samples($key),
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        MessageTemplate::updateOrCreate(['key' => $key], $validated + ['updated_by' => $request->user()->id]);

        $name = MessageTemplates::TEMPLATES[$key]['name'];
        ActivityLogger::log('Settings', 'UPDATE', "Changed the wording of the {$name} message");

        return redirect()->route('admin.message-templates.edit', $key)->with('success', "{$name} saved. It is used from the next message sent.");
    }

    public function reset(string $key): RedirectResponse
    {
        MessageTemplate::where('key', $key)->delete();

        $name = MessageTemplates::TEMPLATES[$key]['name'];
        ActivityLogger::log('Settings', 'RESET', "Reset the {$name} message to its default wording");

        return redirect()->route('admin.message-templates.edit', $key)->with('success', "{$name} is back to its default wording.");
    }
}
