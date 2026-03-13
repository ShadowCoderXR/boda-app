<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';
    public array $categoryIds = [];
    public string $newCategoryName = '';
    public string $groupName = '';
    public ?string $parentGroupId = null;
    public string $subGroupSearch = '';
    public array $newCompanion = ['name' => ''];

    /**
     * Get the current user's guest record.
     */
    #[Computed]
    public function guest(): ?\App\Models\Guest
    {
        return Auth::user()->guest;
    }

    /**
     * Get the user's companions.
     */
    #[Computed]
    public function companions()
    {
        return $this->guest?->members ?? collect();
    }

    /**
     * Get available categories for guests.
     */
    #[Computed]
    public function categories()
    {
        return \App\Models\Category::where('type', 'guest')->get();
    }

    /**
     * Get available potential parent groups (Sub-grupos).
     */
    #[Computed]
    public function potentialSubGroups()
    {
        return \App\Models\Subgroup::where('user_id', auth()->id())
            ->where('name', 'like', '%' . $this->subGroupSearch . '%')
            ->get();
    }

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email ?? '';
        
        if ($this->guest) {
            $this->categoryIds = $this->guest->categories()->pluck('categories.id')->toArray();
            
            if ($this->guest->group) {
                $this->groupName = $this->guest->group->name;
                $this->parentGroupId = (string) $this->guest->group->subgroup_id;
                
                if ($this->guest->group->subgroup) {
                    $this->subGroupSearch = $this->guest->group->subgroup->name;
                }
            }
        }
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users')->ignore($user->id)],
            'categoryIds' => ['array'],
            'categoryIds.*' => ['exists:categories,id'],
            'newCategoryName' => ['nullable', 'string', 'max:255'],
            'groupName' => ['required', 'string', 'max:255'],
            'parentGroupId' => ['nullable', 'string', 'max:255'],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($this->guest) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($validated) {
                // 1. Group Logic (New/Update specific group name)
                $actualParentId = null;
                if (!empty($validated['parentGroupId'])) {
                    if (is_numeric($validated['parentGroupId'])) {
                        $actualParentId = (int) $validated['parentGroupId'];
                    } else {
                        // Create new parent group
                        $newParent = \App\Models\Subgroup::create([
                            'name' => $validated['parentGroupId'],
                            'user_id' => auth()->id()
                        ]);
                        $actualParentId = $newParent->id;
                        $this->parentGroupId = (string) $actualParentId;
                        $this->subGroupSearch = $validated['parentGroupId'];
                    }
                }

                if ($this->guest->group_id) {
                    $this->guest->group->update([
                        'name' => $validated['groupName'],
                        'subgroup_id' => $actualParentId,
                    ]);
                } else {
                    $newGroup = \App\Models\Group::create([
                        'name' => $validated['groupName'],
                        'subgroup_id' => $actualParentId,
                        'user_id' => auth()->id(),
                    ]);
                    $this->guest->update(['group_id' => $newGroup->id]);
                }

                // 2. Category Logic
                $finalCategoryIds = $validated['categoryIds'];
                if (!empty($validated['newCategoryName'])) {
                    $newCat = \App\Models\Category::create([
                        'name' => $validated['newCategoryName'],
                        'type' => 'guest',
                        'user_id' => auth()->id()
                    ]);
                    $finalCategoryIds[] = $newCat->id;
                    $this->newCategoryName = '';
                }

                $this->guest->update(['name' => $validated['name']]);
                $this->guest->categories()->sync($finalCategoryIds);
                $this->categoryIds = $finalCategoryIds;
            });
        }

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Add a companion to the user's guest group.
     */
    public function addCompanion(): void
    {
        $this->validate(['newCompanion.name' => 'required|string|max:255']);

        if (!$this->guest) return;

        $companion = \App\Models\Guest::create([
            'name' => $this->newCompanion['name'],
            'representative_id' => $this->guest->id,
            'user_id' => Auth::id(),
            'rsvp_status' => 'pending',
            'group_id' => $this->guest->group_id,
        ]);

        $this->newCompanion = ['name' => ''];
        $this->dispatch('companion-added');
    }

    /**
     * Remove a companion.
     */
    public function removeCompanion(string $id): void
    {
        $companion = \App\Models\Guest::where('id', $id)
            ->where('representative_id', $this->guest->id)
            ->first();

        if ($companion) {
            $companion->delete();
        }
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Perfil')" :subheading="__('Actualiza tu nombre, correo y detalles de invitado')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Nombre Completo --}}
                <flux:input wire:model="name" :label="__('Nombre Completo')" type="text" required autofocus autocomplete="name" />
                
                {{-- Nombre de Usuario (Solo lectura) --}}
                <flux:input :value="auth()->user()->username" :label="__('Nombre de Usuario')" readonly disabled />

                {{-- Organización: Grupo y Sub-grupo --}}
                <flux:input wire:model="groupName" :label="__('Nombre de tu Familia / Grupo')" placeholder="Ej: Familia Silva López" required />

                {{-- Sub-grupo combobox dinámico --}}
                <div x-data="{ open: false }" class="relative" @click.away="open = false" wire:ignore.self>
                    <flux:label>{{ __('Depende de (Sub-grupo)') }}</flux:label>
                    
                    <div class="relative mt-1">
                        <input 
                            type="text"
                            wire:model.live.debounce.300ms="subGroupSearch" 
                            @focus="open = true"
                            @click="open = !open"
                            placeholder="{{ __('Buscar o crear sub-grupo...') }}"
                            autocomplete="off"
                            class="w-full pl-3 pr-10 py-2 border border-stone-200 dark:border-stone-700 rounded-lg bg-white dark:bg-stone-800 text-sm focus:ring-2 focus:ring-sage-500/20 focus:border-sage-500 transition-all outline-none"
                        />
                        <div @click="open = !open" class="absolute inset-y-0 right-0 flex items-center pr-3 cursor-pointer text-stone-400 hover:text-stone-600">
                            <flux:icon.chevron-down class="w-4 h-4" />
                        </div>
                    </div>
                    
                    <div x-show="open" 
                         x-transition
                         class="absolute z-[9999] w-full mt-1 bg-white dark:bg-stone-800 border border-stone-200 dark:border-stone-700 rounded-lg shadow-xl max-h-60 overflow-y-auto">
                        <div class="p-1">
                            @if(empty($subGroupSearch))
                                <button type="button" wire:click="$set('parentGroupId', ''); subGroupSearch = ''; open = false" class="w-full text-left p-2 hover:bg-stone-100 dark:hover:bg-stone-700 cursor-pointer rounded-md text-sm border-b border-stone-100 dark:border-stone-700 mb-1">
                                    {{ __('Ninguno (General)') }}
                                </button>
                            @endif

                            @foreach($this->potentialSubGroups as $sg)
                                <button type="button" wire:click="$set('parentGroupId', '{{ $sg->id }}'); $set('subGroupSearch', '{{ $sg->name }}'); open = false" class="w-full text-left p-2 hover:bg-stone-100 dark:hover:bg-stone-700 cursor-pointer rounded-md text-sm mb-1">
                                    {{ $sg->name }}
                                </button>
                            @endforeach
                            
                            @if(!empty($subGroupSearch) && !$this->potentialSubGroups->where('name', $subGroupSearch)->count())
                                <button type="button" wire:click="$set('parentGroupId', '{{ $subGroupSearch }}'); open = false" class="w-full text-left p-2 bg-sage-50 dark:bg-sage-900/20 text-sage-600 dark:text-sage-400 hover:bg-sage-100 dark:hover:bg-sage-900/40 cursor-pointer rounded-md text-sm font-medium border border-sage-200 dark:border-sage-800 mt-1">
                                    <flux:icon.plus class="inline-block w-3 h-3 mr-1" />
                                    {{ __('Crear') }}: "{{ $subGroupSearch }}"
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Categorías (Checkboxes) --}}
                <div class="col-span-1 md:col-span-2 space-y-4 pt-4 border-t border-stone-50 dark:border-stone-800">
                    <flux:checkbox.group wire:model="categoryIds" :label="__('Categorías (Puedes marcar varias)')">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            @foreach ($this->categories as $category)
                                <flux:checkbox :label="$category->name" :value="$category->id" />
                            @endforeach
                        </div>
                    </flux:checkbox.group>

                    <div class="flex gap-2 max-w-sm">
                        <flux:input wire:model="newCategoryName" placeholder="{{ __('Añadir nueva categoría...') }}" size="sm" class="flex-1" />
                    </div>
                </div>
            </div>

            <flux:input wire:model="email" :label="__('Correo (Solo para contacto)')" type="email" autocomplete="email" placeholder="Opcional" />

            <div class="flex items-center gap-4 pt-4 border-t border-stone-100 dark:border-stone-800">
                <flux:button variant="primary" type="submit" class="bg-sage-600 hover:bg-sage-700 border-0">
                    {{ __('Guardar Perfil') }}
                </flux:button>

                <x-action-message class="me-3" on="profile-updated">
                    {{ __('Guardado con éxito.') }}
                </x-action-message>
            </div>
        </form>

        <flux:separator class="my-10" />

        <div class="space-y-6">
            <div class="flex flex-col gap-1">
                <flux:heading size="lg">{{ __('Acompañantes y Familia') }}</flux:heading>
                <flux:subheading>{{ __('Administra los invitados adicionales que asistirán contigo.') }}</flux:subheading>
            </div>

            <div class="space-y-4">
                @foreach ($this->companions as $companion)
                    <div class="flex items-center justify-between p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-xl border border-zinc-200 dark:border-zinc-700 transition-all hover:border-sage-300">
                        <div class="flex items-center gap-3">
                            <flux:icon.users class="text-sage-600" />
                            <flux:text class="font-medium">{{ $companion->name }}</flux:text>
                        </div>
                        <flux:button wire:click="removeCompanion('{{ $companion->id }}')" variant="ghost" size="sm" icon="trash" class="text-zinc-400 hover:text-red-500" />
                    </div>
                @endforeach

                <div class="flex gap-2 pt-2">
                    <flux:input wire:model="newCompanion.name" placeholder="Nombre completo del acompañante..." class="flex-1" />
                    <flux:button wire:click="addCompanion" variant="filled" class="bg-gold-600 hover:bg-gold-700 text-white border-0">
                        {{ __('Añadir') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
