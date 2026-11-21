<?php

namespace App\Http\Requests\Agents;

use App\Services\AgentService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAgentRequest extends FormRequest
{
    /** Expiry belongs to the token and is fixed at creation. */
    public function rules(): array
    {
        return collect(AgentService::rules())->except('expires_in_days')->all();
    }
}
