<?php

namespace App\Http\Controllers\Ticketing;

use App\Http\Controllers\Admin\AdminInsurancePlanController;

/**
 * The travel insurance plans, managed by admins from the ticketing portal: the
 * same plans the wizard offers under Contact & Extras, on the same page as the
 * admin's. Ticketing officers use the plans but cannot change them.
 */
class TicketInsurancePlanController extends AdminInsurancePlanController
{
    protected string $routePrefix = 'ticketing.insurance-plans';

    protected string $layout = 'layouts.ticketing';
}
