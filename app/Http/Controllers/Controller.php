<?php
namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Phase 4 adds the first controllers that need `$this->authorize()`
 * (lawyer verification-document access, admin review actions), so the
 * standard Laravel authorization trait is enabled here for every
 * controller rather than re-adding it ad hoc per class.
 */
abstract class Controller
{
    use AuthorizesRequests;
}
