<?php

namespace App\Http\Controllers;

use App\Rules\NotAlreadyRegistered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tells staff, while they type, that a client is already registered.
 *
 * Only reachable from the admin and ticketing desks. The same matching
 * rule blocks the save, so this is the early warning for what would
 * otherwise be refused on submit.
 */
class ClientDuplicateCheckController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'field' => ['required', 'in:email,phone,name'],
            'value' => ['nullable', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:20'],
        ]);

        $rule = (new NotAlreadyRegistered($data['field']))->setData($data);
        $existing = $rule->existing((string) ($data['value'] ?? ''));

        return response()->json([
            'field' => $data['field'],
            'message' => $existing ? $rule->message($existing) : null,
        ]);
    }
}
