<?php

declare(strict_types=1);

namespace App\Http\Requests\CRM;

use App\Http\Requests\Concerns\ScopesToCompany;
use Illuminate\Foundation\Http\FormRequest;

abstract class CrmFormRequest extends FormRequest
{
    use ScopesToCompany;

    public function authorize(): bool
    {
        return true;
    }
}
