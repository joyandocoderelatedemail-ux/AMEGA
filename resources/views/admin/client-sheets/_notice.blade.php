{{--
    Notify an Immigration client, at any status (the panel itself is partials/_notify-client).
    Expects $client. With $caseRow (a row from ImmigrationCases) the template follows that case's status, in the
    wording set under Message Templates; the internal checking steps have none, so the agent writes the message.
    Without one (Visa Extension has no case) the template is the passport pick-up notice.
    Optional $standalone: the block sits on its own line (Visa Extension); otherwise it joins the status row.
--}}
@php
    $caseRow = $caseRow ?? null;
    $notice = $caseRow
        ? \App\Support\CaseNotice::for($caseRow, $client, Auth::user()->name)
        : ['state' => 'ready', 'key' => \App\Support\CaseNotice::RELEASED_KEY] + \App\Support\CaseNotice::pickup($client, Auth::user()->name) + ['message' => null];
    $ready = $notice['state'] === 'ready';
@endphp
@include('partials._notify-client', [
    'noticeFor' => $caseRow ? $caseRow['kind'].'-'.$caseRow['id'] : 'folder',
    'toName' => $client->full_name,
    'email' => $client->email,
    'canEmail' => \App\Services\ClientNotifier::canReceive($client->email),
    'meta' => $caseRow ? $caseRow['reference'].' · '.$caseRow['progress_label'] : null,
    'template' => $ready ? [
        'label' => $caseRow ? $caseRow['progress_label'] : 'the passport pick-up',
        'body' => $notice['body'],
        'editUrl' => Auth::user()->isAdmin() ? route('admin.message-templates.edit', $notice['key']) : null,
        'warning' => $notice['key'] === \App\Support\CaseNotice::RELEASED_KEY && ! $client->visa_expiry_date
            ? 'No visa valid-until date on the folder yet: it reads “To be confirmed”.' : null,
    ] : null,
    'emptyMessage' => $notice['message'],
    'subject' => $notice['subject'] ?? 'Update from AMEGA Travel and Tours',
    'action' => route('admin.client-sheets.pickup-notice', $client),
    'hidden' => $caseRow ? ['kind' => $caseRow['kind'], 'case' => $caseRow['id']] : [],
    'standalone' => $standalone ?? false,
])
