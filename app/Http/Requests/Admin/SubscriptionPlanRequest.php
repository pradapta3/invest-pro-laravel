<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriptionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gated by the 'admin' route middleware already
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $planId = $this->route('plan')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('subscription_plans', 'slug')->ignore($planId)],
            'price_per_month' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'in:'.implode(',', array_keys(config('subscription.features')))],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Turns the textarea (one feature per line) into the JSON array
     * SubscriptionPlan::features actually stores.
     *
     * @return array<string, mixed>
     */
    public function normalizedData(): array
    {
        $data = $this->validated();
        // Feature keys from the checkbox group, kept in the canonical order of
        // config/subscription.php so the plan cards read the same everywhere.
        $ticked = (array) ($data['features'] ?? []);
        $data['features'] = array_values(array_filter(
            array_keys(config('subscription.features')),
            fn (string $key) => in_array($key, $ticked, true),
        ));
        $data['is_active'] = $this->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
