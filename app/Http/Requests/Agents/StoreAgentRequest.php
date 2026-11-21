<?php

namespace App\Http\Requests\Agents;

use App\Services\AgentService;
use Illuminate\Foundation\Http\FormRequest;

class StoreAgentRequest extends FormRequest
{
    public function rules(): array
    {
        return AgentService::rules();
    }
}
