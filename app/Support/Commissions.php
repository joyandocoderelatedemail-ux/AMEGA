<?php

namespace App\Support;

use App\Models\AgentCommission;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The agent's commission on a service, typed in as an amount by an admin when approving it.
 *
 * Only an admin sees or sets it: a supervisor who holds the Approve permission approves without it,
 * and the field is not even shown to them. It is recorded against the agent who sent the file
 * (Approvals::makerOf), so it follows the person who did the work.
 */
class Commissions
{
    /** @var array<string, Collection<string, AgentCommission>> */
    private static array $loaded = [];

    /** Who sees and sets commissions: admins only. */
    public static function visibleTo(?User $user): bool
    {
        return (bool) $user?->isAdmin();
    }

    /**
     * Validation for the commission box on an approve form: an amount, never negative. Anyone but an admin
     * has no such field, so nothing they send is validated or kept.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(?User $user): array
    {
        return self::visibleTo($user) ? ['commission' => ['nullable', 'numeric', 'min:0', 'max:99999999.99']] : [];
    }

    /**
     * Keep the commission an admin typed when approving $file. Left blank, nothing changes (an earlier
     * amount stays); typing 0 records no commission.
     *
     * @param  string  $desk  ticketing, visa_assistance, srrv or immigration
     * @param  string  $kind  the file's kind within the desk (the route's kind, or 'ticket')
     * @param  int|null  $agentId  the agent the commission is for; defaults to who sent the file
     */
    public static function record(Request $request, string $desk, string $kind, Model $file, ?string $reference = null, ?string $client = null, ?int $agentId = null): void
    {
        $user = $request->user();

        if (! self::visibleTo($user) || ! filled($request->input('commission'))) {
            return;
        }

        $amount = round((float) $request->input('commission'), 2);
        $agentId ??= Approvals::makerOf($file);

        $commission = AgentCommission::updateOrCreate(
            ['desk' => $desk, 'kind' => $kind, 'file_id' => $file->getKey()],
            ['agent_id' => $agentId, 'amount' => $amount, 'reference' => $reference, 'client_name' => $client, 'set_by' => $user->id],
        );

        unset(self::$loaded[$desk]);

        $agent = $agentId ? User::find($agentId)?->name : null;
        ActivityLogger::log('Commissions', 'SET', 'Set a ₱'.number_format($amount, 2).' commission for '.($agent ?? 'the agent').' on '.($reference ?? "{$desk} #{$file->getKey()}"), ['commission_id' => $commission->id]);
    }

    /** The commission recorded on a file, or null; one query per desk per request. */
    public static function amount(string $desk, string $kind, int|string $fileId): ?float
    {
        self::$loaded[$desk] ??= AgentCommission::where('desk', $desk)->get()->keyBy(fn (AgentCommission $c): string => $c->kind.':'.$c->file_id);

        $row = self::$loaded[$desk]->get($kind.':'.$fileId);

        return $row ? (float) $row->amount : null;
    }
}
