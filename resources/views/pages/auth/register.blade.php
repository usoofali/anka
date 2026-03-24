<x-layouts::auth.card
    :title="__('Register as a shipper')"
    :description="__('Create your company account to manage shipments and bookings.')"
>
    <form
        method="POST"
        action="{{ route('register.store') }}"
        class="flex flex-col gap-8"
        x-data="{
            countries: @js($geoCountries),
            countryId: @js(old('country_id') !== null && old('country_id') !== '' ? (string) old('country_id') : ''),
            stateId: @js(old('state_id') !== null && old('state_id') !== '' ? (string) old('state_id') : ''),
            cityId: @js(old('city_id') !== null && old('city_id') !== '' ? (string) old('city_id') : ''),
            get states() {
                const c = this.countries.find((x) => String(x.id) === String(this.countryId));
                return c?.states ?? [];
            },
            get cities() {
                const s = this.states.find((x) => String(x.id) === String(this.stateId));
                return s?.cities ?? [];
            },
        }"
    >
        @csrf

        <section class="space-y-4">
            <div class="space-y-1">
                <flux:heading size="sm" level="3">{{ __('Your account') }}</flux:heading>
                <flux:subheading size="sm" class="text-zinc-500 dark:text-zinc-400">
                    {{ __('Name and email you will use to sign in.') }}
                </flux:subheading>
            </div>
            <div class="space-y-4">
                <flux:input name="name" :label="__('Your name')" type="text" required autofocus autocomplete="name" :value="old('name')" />

                <flux:input name="email" :label="__('Email address')" type="email" required autocomplete="username" :value="old('email')" />
            </div>
        </section>

        <flux:separator variant="subtle" />

        <section class="space-y-4">
            <div class="space-y-1">
                <flux:heading size="sm" level="3">{{ __('Company') }}</flux:heading>
                <flux:subheading size="sm" class="text-zinc-500 dark:text-zinc-400">
                    {{ __('Legal or trading name and how we can reach your team.') }}
                </flux:subheading>
            </div>
            <div class="space-y-4">
                <flux:input name="company_name" :label="__('Company name')" type="text" required :value="old('company_name')" />

                <flux:input name="phone" :label="__('Phone')" type="tel" required :value="old('phone')" autocomplete="tel" />

                <flux:input name="address" :label="__('Address')" type="text" required :value="old('address')" autocomplete="street-address" />
            </div>
        </section>

        <flux:separator variant="subtle" />

        <section class="space-y-4">
            <div class="space-y-1">
                <flux:heading size="sm" level="3">{{ __('Business location') }}</flux:heading>
                <flux:subheading size="sm" class="text-zinc-500 dark:text-zinc-400">
                    {{ __('Where your company is based.') }}
                </flux:subheading>
            </div>
            <div class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Country') }}</flux:label>
                    <select
                        name="country_id"
                        x-model="countryId"
                        @change="stateId = ''; cityId = '';"
                        class="block w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-xs focus:border-zinc-950 focus:ring-2 focus:ring-zinc-950/10 focus:outline-hidden dark:border-white/10 dark:bg-zinc-900 dark:text-white dark:focus:border-white dark:focus:ring-white/20"
                        required
                    >
                        <option value="">{{ __('Select country') }}</option>
                        <template x-for="c in countries" :key="c.id">
                            <option :value="String(c.id)" x-text="c.name"></option>
                        </template>
                    </select>
                    <flux:error name="country_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('State / region') }}</flux:label>
                    <select
                        name="state_id"
                        x-model="stateId"
                        @change="cityId = '';"
                        class="block w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-xs focus:border-zinc-950 focus:ring-2 focus:ring-zinc-950/10 focus:outline-hidden dark:border-white/10 dark:bg-zinc-900 dark:text-white dark:focus:border-white dark:focus:ring-white/20"
                        required
                    >
                        <option value="">{{ __('Select state') }}</option>
                        <template x-for="s in states" :key="s.id">
                            <option :value="String(s.id)" x-text="s.name"></option>
                        </template>
                    </select>
                    <flux:error name="state_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('City') }}</flux:label>
                    <select
                        name="city_id"
                        x-model="cityId"
                        class="block w-full rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 shadow-xs focus:border-zinc-950 focus:ring-2 focus:ring-zinc-950/10 focus:outline-hidden dark:border-white/10 dark:bg-zinc-900 dark:text-white dark:focus:border-white dark:focus:ring-white/20"
                        required
                    >
                        <option value="">{{ __('Select city') }}</option>
                        <template x-for="ci in cities" :key="ci.id">
                            <option :value="String(ci.id)" x-text="ci.name"></option>
                        </template>
                    </select>
                    <flux:error name="city_id" />
                </flux:field>
            </div>
        </section>

        <flux:separator variant="subtle" />

        <section class="space-y-4">
            <div class="space-y-1">
                <flux:heading size="sm" level="3">{{ __('Security') }}</flux:heading>
                <flux:subheading size="sm" class="text-zinc-500 dark:text-zinc-400">
                    {{ __('Choose a strong password for your account.') }}
                </flux:subheading>
            </div>
            <div class="space-y-4">
                <flux:input name="password" :label="__('Password')" type="password" required autocomplete="new-password" viewable />

                <flux:input name="password_confirmation" :label="__('Confirm password')" type="password" required autocomplete="new-password" viewable />
            </div>
        </section>

        <flux:separator variant="subtle" />

        <flux:field variant="inline">
            <flux:checkbox name="terms" value="1" :checked="(bool) old('terms')" />
            <flux:label>{{ __('I agree to the terms and conditions') }}</flux:label>
            <flux:error name="terms" />
        </flux:field>

        <div class="flex items-center justify-end">
            <flux:button variant="primary" type="submit" class="w-full">{{ __('Create account') }}</flux:button>
        </div>
    </form>

    <div class="space-x-1 text-center text-sm text-zinc-600 rtl:space-x-reverse dark:text-zinc-400">
        {{ __('Already have an account?') }}
        <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
    </div>
</x-layouts::auth.card>
