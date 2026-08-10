<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Http\Controllers\AjaxController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Forwards an API call into the portal's shared AJAX dispatcher.
 *
 * The mobile API deliberately owns no business logic. Each endpoint translates
 * its route + payload into the AJAX key the portal already handles and returns
 * that response verbatim, so the app and the browser cannot drift apart, and a
 * fix in AjaxController lands on both at once.
 *
 * The sub-request is built from an explicit whitelist rather than the incoming
 * request: AjaxController picks its action by scanning for the first known key
 * present, so blindly forwarding client input could select a different action
 * than the route intended.
 */
trait BridgesAjax
{
    /**
     * @param Request|null $uploadsFrom Forward this request's uploaded files to
     *        the dispatcher. Needed by endpoints that accept photos — files
     *        live in their own bag and are not carried by $params.
     */
    protected function ajax(string $key, array $params = [], ?Request $uploadsFrom = null): JsonResponse
    {
        // Nulls travel. They used to be filtered out here, which meant no text
        // field could ever be cleared from the app: an emptied box arrives as
        // "" , the framework's ConvertEmptyStringsToNull turns it into null,
        // and dropping it left the handler seeing nothing at all — so the old
        // value stayed and the member was told the profile had been updated.
        // A browser form posts an emptied field exactly this way, and only()
        // already omits keys the caller never sent.
        $payload = array_merge($params, [$key => 1]);

        $sub = Request::create(
            '/ajax',
            'POST',
            $payload,
            [],
            $uploadsFrom ? $uploadsFrom->allFiles() : [],
        );
        $sub->setUserResolver(fn() => Auth::user());

        return app(AjaxController::class)->index($sub);
    }

    /**
     * Pull only the named keys off the request. Absent keys are dropped rather
     * than sent as null so the portal's `filled()` / `has()` checks behave the
     * same way they do for a form post.
     */
    protected function only(Request $request, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            if ($request->has($key)) {
                $out[$key] = $request->input($key);
            }
        }
        return $out;
    }
}
