<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\City;
use App\Models\Shipper;
use App\Models\State;
use App\Models\User;
use App\Notifications\ShipperRegisteredInternalNotification;
use App\Notifications\ShipperWelcomeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'terms' => ['accepted'],
            'company_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:500'],
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
        ])->after(function ($validator) use ($input): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $state = State::query()->find($input['state_id']);
            if (! $state || (int) $state->country_id !== (int) $input['country_id']) {
                $validator->errors()->add('state_id', __('The selected state does not belong to the chosen country.'));
            }

            $city = City::query()->find($input['city_id']);
            if (! $city || (int) $city->state_id !== (int) $input['state_id']) {
                $validator->errors()->add('city_id', __('The selected city does not belong to the chosen state.'));
            }
        })->validate();

        $user = DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $shipper = Shipper::create([
                'user_id' => $user->id,
                'company_name' => $input['company_name'],
                'phone' => $input['phone'],
                'address' => $input['address'],
                'country_id' => $input['country_id'],
                'state_id' => $input['state_id'],
                'city_id' => $input['city_id'],
            ]);

            $user->assignRole('shipper');

            $user->notify(new ShipperWelcomeNotification($shipper));

            $recipientIds = User::query()
                ->role('super_admin')
                ->pluck('id')
                ->merge(
                    User::query()->whereHas('staff')->pluck('id'),
                )
                ->unique()
                ->filter(fn (int|string $id): bool => (int) $id !== (int) $user->id)
                ->values();

            $recipients = User::query()->whereIn('id', $recipientIds)->get();

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new ShipperRegisteredInternalNotification($user, $shipper));
            }

            return $user;
        });

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Welcome! Your shipper account has been created.'),
            'timeout' => 6000,
        ]);

        return $user;
    }
}
