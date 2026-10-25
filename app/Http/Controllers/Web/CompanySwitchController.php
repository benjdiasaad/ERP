<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanySwitchController extends Controller
{
    /**
     * Switch the current company. Only companies the user belongs to are allowed.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'company_id' => [
                'required',
                'integer',
                Rule::exists('company_user', 'company_id')->where('user_id', $user->id),
            ],
        ]);

        $user->update(['current_company_id' => (int) $data['company_id']]);

        // Back to the dashboard: the previous page may show a record of the old company.
        return redirect()->route('dashboard')->with('success', 'Société changée.');
    }
}
