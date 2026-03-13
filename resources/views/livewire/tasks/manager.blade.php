<?php

use Livewire\Volt\Component;
use App\Models\Task;
use App\Models\Sponsor;
use Livewire\Attributes\Url;

new class extends Component {
    // Create Form State
    public $newTitle = '';
    public $newDescription = '';
    public $newDueDate = '';
    public $newPriority = 'low';
    public $newAssignedToId = '';
    public $newCategoryId = '';
    public $newCategorySearch = '';

    // Edit Form State
    public $title = '';
    public $description = '';
    public $due_date = '';
    public $priority = 'low';
    public $assigned_to_id = '';
    public $category_id = '';
    public $categorySearch = '';

    public $editingTask = null;
    public $taskDeletingId = null;
    public $newComment = '';
    
    #[Url]
    public $filterStatus = 'all'; // all, pending, completed

    #[Url]
    public $date = '';

    #[Url]
    public $filterCategoryId = '';

    public function with()
    {
        $query = Task::with(['sponsor.guest', 'comments.user'])->where('user_id', auth()->id());

        if ($this->filterStatus === 'pending') {
            $query->where('is_completed', false);
        } elseif ($this->filterStatus === 'completed') {
            $query->where('is_completed', true);
        }

        if ($this->date) {
            $query->whereDate('due_date', $this->date);
        }
        
        if ($this->filterCategoryId) {
            $query->where('category_id', $this->filterCategoryId);
        }

        return [
            'tasks' => $query->orderBy('due_date', 'asc')->orderBy('priority', 'desc')->get(),
            'teamMembers' => \App\Models\User::orderBy('name')->get(),
            'taskCategories' => \App\Models\Category::where('type', 'task')
                ->where('user_id', auth()->id())
                ->where(function($q) {
                    $q->where('name', 'like', '%'.$this->categorySearch.'%')
                      ->orWhere('name', 'like', '%'.$this->newCategorySearch.'%');
                })
                ->orderBy('name')
                ->get(),
            'filterCategories' => \App\Models\Category::where('type', 'task')
                ->where('user_id', auth()->id())
                ->orderBy('name')
                ->get(),
        ];
    }

    public function createTask()
    {
        $this->validate([
            'newTitle' => 'required|string|max:255',
            'newPriority' => 'required|in:low,medium,high',
            'newAssignedToId' => 'nullable|exists:users,id',
            'newDueDate' => 'nullable|date',
        ], [], [
            'newTitle' => 'título',
            'newPriority' => 'prioridad',
            'newAssignedToId' => 'responsable',
            'newDueDate' => 'fecha límite',
        ]);

        // Handle Category logic
        $actualCategoryId = $this->newCategoryId;
        if (empty($actualCategoryId)) {
            $actualCategoryId = $this->ensureGeneralCategoryExists()->id;
        } elseif (!Str::isUuid($actualCategoryId)) {
            $newCat = \App\Models\Category::create([
                'name' => $actualCategoryId,
                'type' => 'task',
                'user_id' => auth()->id(),
                'color' => 'sage'
            ]);
            $actualCategoryId = $newCat->id;
        }

        Task::create([
            'title' => $this->newTitle,
            'description' => $this->newDescription,
            'due_date' => $this->newDueDate ?: null,
            'priority' => $this->newPriority,
            'assigned_to_id' => $this->newAssignedToId ?: null,
            'category_id' => $actualCategoryId ?: null,
            'user_id' => auth()->id(),
        ]);

        $this->reset(['newTitle', 'newDescription', 'newDueDate', 'newPriority', 'newAssignedToId', 'newCategoryId', 'newCategorySearch']);
        Flux::toast('Tarea creada correctamente.');
    }

    public function editTask(Task $task)
    {
        $this->editingTask = $task->load('comments.user');
        $this->title = $task->title;
        $this->description = $task->description;
        $this->due_date = $task->due_date ? $task->due_date->format('Y-m-d') : '';
        $this->priority = $task->priority;
        $this->assigned_to_id = $task->assigned_to_id;
        $this->category_id = $task->category_id;

        $this->modal('edit-task-modal')->show();
    }

    public function updateTask()
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'priority' => 'required|in:low,medium,high',
            'assigned_to_id' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
        ], [], [
            'title' => 'título',
        ]);

        // Handle Category logic
        $actualCategoryId = $this->category_id;
        if (empty($actualCategoryId)) {
            $actualCategoryId = $this->ensureGeneralCategoryExists()->id;
        } elseif (!Str::isUuid($actualCategoryId)) {
            $newCat = \App\Models\Category::create([
                'name' => $actualCategoryId,
                'type' => 'task',
                'user_id' => auth()->id(),
                'color' => 'sage'
            ]);
            $actualCategoryId = $newCat->id;
        }

        $this->editingTask->update([
            'title' => $this->title,
            'description' => $this->description,
            'due_date' => $this->due_date ?: null,
            'priority' => $this->priority,
            'assigned_to_id' => $this->assigned_to_id ?: null,
            'category_id' => $actualCategoryId ?: null,
        ]);

        $this->resetForms();
        Flux::toast('Tarea actualizada correctamente.');
    }

    public function resetForms()
    {
        $this->reset(['newTitle', 'newDescription', 'newDueDate', 'newPriority', 'newAssignedToId', 'newCategoryId', 'newCategorySearch', 'title', 'description', 'due_date', 'priority', 'assigned_to_id', 'category_id', 'categorySearch', 'editingTask', 'taskDeletingId']);
        $this->modal('edit-task-modal')->close();
        $this->modal('confirm-delete-task')->close();
    }

    private function ensureGeneralCategoryExists()
    {
        return \App\Models\Category::firstOrCreate([
            'name' => 'General',
            'type' => 'task',
            'user_id' => auth()->id(),
        ], [
            'color' => 'stone',
        ]);
    }

    public function addComment()
    {
        if (empty(trim($this->newComment))) return;

        $this->editingTask->comments()->create([
            'user_id' => auth()->id(),
            'content' => $this->newComment,
        ]);

        $this->newComment = '';
        $this->editingTask->load('comments.user');
        Flux::toast('Comentario añadido.');
    }

    public function toggleTask(Task $task)
    {
        $task->update(['is_completed' => !$task->is_completed]);
    }

    public function confirmDelete(Task $task)
    {
        $this->taskDeletingId = $task->id;
        $this->modal('confirm-delete-task')->show();
    }

    public function deleteTask()
    {
        if (!$this->taskDeletingId) return;
        
        $task = Task::find($this->taskDeletingId);
        if ($task) {
            $task->delete();
        }
        
        $this->resetForms();
        Flux::toast('Tarea eliminada.');
    }
}
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Pendientes y Avisos</flux:heading>
            <flux:subheading>Organiza las tareas de la boda, asigna responsabilidades y lleva un registro del progreso.</flux:subheading>
        </div>
        <div class="flex gap-4 items-center">
            <div class="flex items-center gap-2">
                <flux:label class="hidden md:block text-xs font-semibold text-stone-500 uppercase tracking-wider">{{ __('Fecha') }}:</flux:label>
                <flux:input wire:model.live="date" type="date" size="sm" class="w-32" />
            </div>

            <div class="flex items-center gap-2">
                <flux:label class="hidden md:block text-xs font-semibold text-stone-500 uppercase tracking-wider">{{ __('Categoría') }}:</flux:label>
                <flux:select wire:model.live="filterCategoryId" size="sm" class="w-40" placeholder="Todas">
                    <flux:select.option value="">Todas</flux:select.option>
                    @foreach($filterCategories as $fcat)
                        <flux:select.option value="{{ $fcat->id }}">{{ $fcat->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:radio.group wire:model.live="filterStatus" variant="segmented" size="sm">
                <flux:radio value="all" label="Todas" />
                <flux:radio value="pending" label="Pendientes" />
                <flux:radio value="completed" label="Completadas" />
            </flux:radio.group>
        </div>
    </div>

    @if($filterStatus === 'all' && $tasks->isEmpty())
        <div class="bg-sage-50 border border-sage-100 p-4 rounded-2xl flex gap-3">
            <flux:icon.information-circle class="text-sage-600 shrink-0" />
            <div class="text-sm text-sage-800">
                <p class="font-bold mb-1">Guía rápida de gestión:</p>
                <ul class="list-disc list-inside space-y-1 opacity-90">
                    <li><b>Pendientes:</b> Tareas por hacer.</li>
                    <li><b>Completadas:</b> Tareas que ya marcaste como listas.</li>
                    <li><b>Notas:</b> Puedes agregar detalles de seguimiento editando cualquier tarea.</li>
                </ul>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Create Task Form --}}
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-stone-800 p-6 rounded-2xl shadow-sm border border-stone-200 dark:border-stone-700">
                <flux:heading size="lg" class="mb-4">Nueva Tarea</flux:heading>
                <form wire:submit="createTask" class="space-y-4">
                    <div>
                        <flux:label>Título <span class="text-red-500">*</span></flux:label>
                        <flux:input wire:model="newTitle" placeholder="Ej: Reservar fotógrafo" />
                    </div>
                    <flux:textarea wire:model="newDescription" label="Descripción (opcional)" dense />
                    
                    <div class="grid grid-cols-2 gap-4">
                        <flux:input wire:model="newDueDate" type="date" label="Fecha límite" />
                        
                        <div x-data="{ open: false }" class="relative" @click.away="open = false" wire:ignore.self>
                            <flux:label>Categoría</flux:label>
                            
                            <div class="relative mt-1" @click="open = true">
                                <input 
                                    type="text"
                                    wire:model.live.debounce.300ms="newCategorySearch" 
                                    @focus="open = true"
                                    placeholder="Buscar o crear..."
                                    autocomplete="off"
                                    class="w-full pl-3 pr-10 py-2 border border-stone-200 dark:border-stone-700 rounded-lg bg-white dark:bg-stone-800 text-sm focus:ring-2 focus:ring-sage-500/20 focus:border-sage-500 transition-all outline-none"
                                />
                                <div @click="open = !open" class="absolute inset-y-0 right-0 flex items-center pr-3 cursor-pointer text-stone-400 hover:text-stone-600">
                                    <flux:icon.chevron-down class="w-4 h-4" />
                                </div>
                            </div>
                            
                            <div x-show="open" 
                                 x-transition
                                 class="absolute z-50 w-full mt-1 bg-white dark:bg-stone-800 border-2 border-sage-500/50 rounded-lg shadow-2xl max-h-60 overflow-y-auto">
                                <div class="p-1">
                                    @if(empty($newCategorySearch))
                                        <button type="button" wire:click="$set('newCategoryId', 'General'); $set('newCategorySearch', 'General'); open = false" class="w-full text-left p-2 hover:bg-stone-100 dark:hover:bg-stone-700 cursor-pointer rounded-md text-sm border-b border-stone-100 dark:border-stone-700 mb-1">
                                            General
                                        </button>
                                    @endif

                                    @foreach($taskCategories as $cat)
                                        <button type="button" wire:click="$set('newCategoryId', '{{ $cat->id }}'); $set('newCategorySearch', '{{ $cat->name }}'); open = false" class="w-full text-left p-2 hover:bg-stone-100 dark:hover:bg-stone-700 cursor-pointer rounded-md text-sm mb-1">
                                            {{ $cat->name }}
                                        </button>
                                    @endforeach
                                    
                                    @if(!empty($newCategorySearch) && !$taskCategories->where('name', $newCategorySearch)->count())
                                        <button type="button" wire:click="$set('newCategoryId', '{{ $newCategorySearch }}'); open = false" class="w-full text-left p-2 bg-sage-50 dark:bg-sage-900/20 text-sage-600 dark:text-sage-400 hover:bg-sage-100 dark:hover:bg-sage-900/40 cursor-pointer rounded-md text-sm font-medium border border-sage-200 dark:border-sage-800 mt-1">
                                            <flux:icon.plus class="inline-block w-3 h-3 mr-1" />
                                            Crear: "{{ $newCategorySearch }}"
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <flux:select wire:model="newPriority" label="Prioridad">
                        <flux:select.option value="low">Baja</flux:select.option>
                        <flux:select.option value="medium">Media</flux:select.option>
                        <flux:select.option value="high">Alta</flux:select.option>
                    </flux:select>

                    <flux:select wire:model="newAssignedToId" label="Asignar a (Responsable)" placeholder="Ninguno (Global)">
                        <flux:select.option value="">Global / Sin asignar</flux:select.option>
                        @foreach($teamMembers as $member)
                            <flux:select.option value="{{ $member->id }}">{{ $member->name }} ({{ $member->role->label() }})</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:button type="submit" variant="primary" class="w-full bg-sage-600 hover:bg-sage-700 border-0">Crear Tarea</flux:button>
                </form>
            </div>
        </div>

        {{-- Tasks List --}}
        <div class="lg:col-span-2 space-y-4">
            @forelse($tasks as $task)
                <div class="bg-white dark:bg-stone-900 p-4 rounded-2xl shadow-sm border border-stone-100 dark:border-stone-800 flex items-center gap-4 group">
                    <button wire:click="toggleTask({{ $task->id }})" class="shrink-0 focus:outline-none">
                        @if($task->is_completed)
                            <div class="w-6 h-6 rounded-full bg-sage-500 flex items-center justify-center text-white">
                                <flux:icon.check class="w-4 h-4" />
                            </div>
                        @else
                            <div class="w-6 h-6 rounded-full border-2 border-stone-300 dark:border-stone-700 hover:border-sage-400"></div>
                        @endif
                    </button>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <button wire:click="editTask({{ $task->id }})" class="text-left focus:outline-none">
                                <h4 class="text-sm font-semibold hover:text-sage-600 transition-colors @if($task->is_completed) line-through text-stone-400 @else text-stone-800 dark:text-stone-200 @endif">
                                    {{ $task->title }}
                                </h4>
                            </button>
                            <span class="px-2 py-0.5 rounded text-[10px] uppercase font-bold tracking-wider 
                                @if($task->priority === 'high') bg-red-100 text-red-600 @elseif($task->priority === 'medium') bg-amber-100 text-amber-600 @else bg-stone-100 text-stone-500 @endif">
                                {{ $task->priority === 'low' ? 'Baja' : ($task->priority === 'medium' ? 'Media' : 'Alta') }}
                            </span>
                            @if($task->category)
                                <span class="px-2 py-0.5 rounded bg-{{ $task->category->color }}-100 text-{{ $task->category->color }}-600 text-[10px] uppercase font-bold tracking-wider">
                                    {{ $task->category->name }}
                                </span>
                            @endif
                            @if($task->assignedTo)
                                <span class="px-2 py-0.5 rounded bg-sky-100 text-sky-600 text-[10px] uppercase font-bold tracking-wider flex items-center gap-1">
                                    <flux:icon.user class="w-2 h-2" />
                                    {{ $task->assignedTo->name }}
                                </span>
                            @endif
                        </div>
                        
                        @if($task->description)
                            <p class="text-xs text-stone-500 line-clamp-1 mb-1">{{ $task->description }}</p>
                        @endif

                        @php
                            $lastComment = $task->comments->last();
                        @endphp
                        @if($lastComment)
                            <div class="bg-stone-50 dark:bg-stone-800/50 p-2 rounded-lg border-l-2 border-sage-300 mb-1">
                                <p class="text-[11px] text-stone-600 dark:text-stone-400 italic">
                                    <flux:icon.chat-bubble-bottom-center-text class="w-3 h-3 inline mr-1 opacity-50" />
                                    {{ $lastComment->content }}
                                </p>
                            </div>
                        @endif

                        <div class="flex items-center gap-3 text-[10px] text-stone-400">
                            @if($task->due_date)
                                <span class="flex items-center gap-1 @if($task->due_date->isPast() && !$task->is_completed) text-red-500 @endif">
                                    <flux:icon.calendar class="w-3 h-3" />
                                    {{ $task->due_date->format('d M, Y') }}
                                </span>
                            @endif
                            <span class="flex items-center gap-1">
                                <flux:icon.clock class="w-3 h-3" />
                                Hace {{ $task->created_at->diffForHumans() }}
                            </span>
                        </div>
                    </div>

                    <flux:dropdown>
                        <flux:button variant="ghost" icon="ellipsis-vertical" size="sm" class="opacity-0 group-hover:opacity-100 transition-opacity" />
                        <flux:menu>
                            <flux:menu.item icon="pencil" wire:click="editTask({{ $task->id }})">Editar / Notas</flux:menu.item>
                            <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $task->id }})">Eliminar</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </div>
            @empty
                <div class="text-center py-12 bg-stone-50 dark:bg-stone-900 border-2 border-dashed border-stone-200 dark:border-stone-800 rounded-2xl">
                    <flux:icon.clipboard-document-list class="w-12 h-12 mx-auto mb-4 text-stone-300" />
                    <flux:heading size="lg" class="text-stone-400">Sin tareas pendientes</flux:heading>
                    <flux:text>Empieza a organizar tu lista de pendientes arriba.</flux:text>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Edit Task Modal --}}
    <flux:modal name="edit-task-modal" variant="bare" class="w-full" x-on:task-updated.window="$el.close()">
        <div class="mx-auto md:w-[900px] lg:w-[1200px] w-full bg-white dark:bg-stone-900 shadow-2xl rounded-3xl overflow-hidden border border-stone-100 dark:border-stone-800 relative">
            <div class="relative grid grid-cols-1 lg:grid-cols-[1.5fr_1fr] divide-y lg:divide-y-0 lg:divide-x divide-stone-100 dark:divide-stone-800">
                {{-- Left Side: Task Form --}}
                <div class="p-10 space-y-10 overflow-y-auto max-h-[90vh]">
                    <div>
                        <flux:heading size="xl" class="text-stone-800 dark:text-stone-100">Detalles del Pendiente</flux:heading>
                        <flux:subheading>Actualiza la información y seguimiento de la tarea.</flux:subheading>
                    </div>

                    <form wire:submit="updateTask" class="space-y-8">
                        <div>
                            <flux:label class="text-stone-500 font-medium">Título <span class="text-red-500">*</span></flux:label>
                            <flux:input wire:model="title" placeholder="Título de la tarea" class="mt-2 text-lg shadow-sm" />
                        </div>

                        <div>
                            <flux:label class="text-stone-500 font-medium">Descripción</flux:label>
                            <flux:textarea wire:model="description" placeholder="Detalles adicionales..." rows="5" class="mt-2 shadow-sm" />
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                            <div>
                                <flux:label class="text-stone-500 font-medium mb-2 block">Fecha límite</flux:label>
                                <flux:input wire:model="due_date" type="date" class="shadow-sm" />
                            </div>
                            
                            <div x-data="{ open: false }" class="relative" @click.away="open = false" wire:ignore.self>
                                <flux:label class="text-stone-500 font-medium mb-2 block">Categoría</flux:label>
                                
                                <div class="relative" @click="open = true">
                                    <input 
                                        type="text"
                                        wire:model.live.debounce.300ms="categorySearch" 
                                        @focus="open = true"
                                        placeholder="Buscar o crear..."
                                        autocomplete="off"
                                        class="w-full pl-3 pr-10 py-2.5 border border-stone-200 dark:border-stone-700 rounded-xl bg-white dark:bg-stone-800 text-sm shadow-sm focus:ring-2 focus:ring-sage-500/20 focus:border-sage-500 transition-all outline-none"
                                    />
                                    <div @click="open = !open" class="absolute inset-y-0 right-0 flex items-center pr-3 cursor-pointer text-stone-400 hover:text-stone-600">
                                        <flux:icon.chevron-down class="w-4 h-4" />
                                    </div>
                                </div>
                                
                                <div x-show="open" 
                                     x-transition
                                     class="absolute z-[9999] w-full mt-2 bg-white dark:bg-stone-800 border-2 border-sage-500/50 rounded-xl shadow-2xl max-h-64 overflow-y-auto">
                                    <div class="p-2">
                                        @if(empty($categorySearch))
                                            <button type="button" wire:click="$set('category_id', 'General'); $set('categorySearch', 'General'); open = false" class="w-full text-left p-3 hover:bg-stone-50 dark:hover:bg-stone-700 cursor-pointer rounded-lg text-sm border-b border-stone-100 dark:border-stone-700 mb-1 transition-colors">
                                                General
                                            </button>
                                        @endif

                                        @foreach($taskCategories as $cat)
                                            <button type="button" wire:click="$set('category_id', '{{ $cat->id }}'); $set('categorySearch', '{{ $cat->name }}'); open = false" class="w-full text-left p-3 hover:bg-stone-50 dark:hover:bg-stone-700 cursor-pointer rounded-lg text-sm mb-1 transition-colors">
                                                {{ $cat->name }}
                                            </button>
                                        @endforeach
                                        
                                        @if(!empty($categorySearch) && !$taskCategories->where('name', $categorySearch)->count())
                                            <button type="button" wire:click="$set('category_id', '{{ $categorySearch }}'); open = false" class="w-full text-left p-3 bg-sage-50 dark:bg-sage-900/20 text-sage-700 dark:text-sage-400 hover:bg-sage-100 dark:hover:bg-sage-900/40 cursor-pointer rounded-lg text-sm font-bold border border-sage-200 dark:border-sage-800 mt-2 transition-all">
                                                <flux:icon.plus class="inline-block w-4 h-4 mr-2" />
                                                Crear: "{{ $categorySearch }}"
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                            <flux:select wire:model="priority" label="Prioridad" class="shadow-sm">
                                <flux:select.option value="low">Baja</flux:select.option>
                                <flux:select.option value="medium">Media</flux:select.option>
                                <flux:select.option value="high">Alta</flux:select.option>
                            </flux:select>

                            <flux:select wire:model="assigned_to_id" label="Responsable" class="shadow-sm">
                                <flux:select.option value="">Global / Sin asignar</flux:select.option>
                                @foreach($teamMembers as $member)
                                    <flux:select.option value="{{ $member->id }}">{{ $member->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>

                        <div class="flex gap-4 justify-end pt-10 border-t border-stone-100 dark:border-stone-800">
                            <button type="button" x-on:click="Flux.modal('edit-task-modal').close()" class="px-6 py-2.5 text-sm font-medium text-stone-500 hover:text-stone-800 dark:hover:text-stone-200 transition-colors">
                                Cancelar
                            </button>
                            <flux:button type="submit" variant="primary" class="bg-sage-600 hover:bg-sage-700 border-0 px-10 py-2.5 text-base rounded-xl shadow-lg shadow-sage-600/20">Guardar Cambios</flux:button>
                        </div>
                    </form>
                </div>
                
                {{-- Right Side: Timeline & Comments --}}
                <div class="p-10 flex flex-col h-full bg-stone-50/50 dark:bg-stone-900/40 border-l border-stone-100 dark:border-stone-800">
                    <div class="flex items-center justify-between mb-8">
                        <div>
                            <flux:heading size="xl" class="text-stone-800 dark:text-stone-100">Línea del Tiempo</flux:heading>
                            <flux:subheading>Seguimiento y comentarios.</flux:subheading>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto max-h-[500px] mb-8 pr-4 space-y-8 relative custom-scrollbar">
                        {{-- Timeline vertical line --}}
                        <div class="absolute left-4.5 top-2 bottom-2 w-0.5 bg-sage-100 dark:bg-sage-900/30 rounded-full"></div>

                        @if($editingTask)
                            @forelse($editingTask->comments->sortByDesc('created_at') as $comment)
                                <div class="relative pl-12">
                                    {{-- Timeline Dot --}}
                                    <div class="absolute left-[13px] top-2.5 w-4 h-4 rounded-full border-4 border-stone-50 dark:border-stone-900 bg-sage-500 shadow-sm z-10 transition-transform hover:scale-125"></div>
                                    
                                    <div class="bg-white dark:bg-stone-800 p-5 rounded-2xl shadow-sm border border-stone-100 dark:border-stone-800 hover:shadow-md transition-shadow">
                                        <div class="flex items-center justify-between mb-3">
                                            <span class="text-xs font-bold text-sage-600 dark:text-sage-400 flex items-center gap-2">
                                                <div class="w-2 h-2 rounded-full bg-sage-400"></div>
                                                {{ $comment->user->name }}
                                            </span>
                                            <span class="text-[10px] text-stone-400 font-bold uppercase tracking-wider backdrop-blur-sm bg-stone-50/50 dark:bg-stone-900/50 px-2 py-0.5 rounded-full">
                                                {{ $comment->created_at->diffForHumans() }}
                                            </span>
                                        </div>
                                        <p class="text-sm text-stone-600 dark:text-stone-300 leading-relaxed">
                                            {{ $comment->content }}
                                        </p>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-24 opacity-30">
                                    <flux:icon.chat-bubble-left-right class="w-16 h-16 mx-auto mb-4 text-stone-300" />
                                    <p class="text-sm font-bold text-stone-400">Sin comentarios aún.</p>
                                    <p class="text-xs mt-2">Mantén informada a tu pareja del progreso.</p>
                                </div>
                            @endforelse
                        @endif
                    </div>

                    {{-- Add Comment Form --}}
                    <div class="mt-auto pt-8 border-t border-stone-200 dark:border-stone-700">
                        <div class="flex items-center gap-3">
                            <flux:input wire:model="newComment" 
                                wire:keydown.enter.prevent="addComment" 
                                placeholder="Escribe un comentario..." 
                                class="flex-1 shadow-inner h-12 rounded-xl text-sm"
                                dense />
                            <flux:button icon="paper-airplane" size="sm" variant="filled" class="bg-sage-500 hover:bg-sage-600 border-0 h-10 w-10 shrink-0 rounded-xl shadow-lg shadow-sage-500/20 transition-all hover:scale-105" wire:click="addComment" />
                        </div>
                    </div>
                </div>
            </div>

            {{-- Absolute Close Button --}}
            <button x-on:click="Flux.modal('edit-task-modal').close()" class="absolute top-6 right-6 p-2 text-stone-400 hover:text-stone-600 dark:hover:text-stone-200 transition-colors z-50">
                <flux:icon.x-mark class="w-6 h-6" />
            </button>
        </div>
    </flux:modal>

    {{-- Delete Confirmation Modal --}}
    <flux:modal name="confirm-delete-task" class="max-w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">¿Eliminar tarea?</flux:heading>
                <flux:subheading>Esta acción no se puede deshacer y eliminará todos los comentarios asociados.</flux:subheading>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancelar</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="deleteTask">Eliminar</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
