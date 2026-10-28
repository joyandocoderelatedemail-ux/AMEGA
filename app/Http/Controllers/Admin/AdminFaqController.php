<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The questions and answers on the public website's FAQ: add, edit, hide and remove them.
 */
class AdminFaqController extends Controller
{
    public function index(): View
    {
        return view('admin.faqs.index', ['faqs' => Faq::orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $faq = Faq::create($this->validated($request) + ['sort_order' => (int) Faq::max('sort_order') + 1]);

        ActivityLogger::log('FAQs', 'CREATE', "Added the FAQ \"{$faq->question}\"");

        return redirect()->route('admin.faqs.index')->with('success', 'Question added.');
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $faq->update($this->validated($request) + $request->validate(['sort_order' => ['required', 'integer', 'min:0', 'max:9999']]));

        ActivityLogger::log('FAQs', 'UPDATE', "Edited the FAQ \"{$faq->question}\"");

        return redirect()->route('admin.faqs.index')->with('success', 'Question saved.');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        ActivityLogger::log('FAQs', 'DELETE', "Removed the FAQ \"{$faq->question}\"");

        return redirect()->route('admin.faqs.index')->with('success', 'Question removed.');
    }

    /** @return array{question: string, answer: string, is_published: bool} */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:3000'],
        ]);

        return [
            'question' => trim($validated['question']),
            'answer' => trim($validated['answer']),
            'is_published' => $request->boolean('is_published'),
        ];
    }
}
