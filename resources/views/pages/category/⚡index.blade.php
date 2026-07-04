<?php
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use App\Models\Category;

new class extends Component {
    use WithPagination;

    public $sortBy = 'name';
    public $sortDirection = 'asc';
    public $search = '';
    public $filterType = '';

    public function sort($column) {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    public function updatingSearch() { $this->resetPage(); }
    public function updatingFilterType() { $this->resetPage(); }

    #[Computed]
    public function categories()
    {
        return Category::query()
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->when($this->filterType, fn($q) => $q->where('type', $this->filterType))
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(10);
    }

    public function edit($id) {
        $this->dispatch('edit-category', id: $id);
    }
};?>

<div class="max-w-7xl mx-auto space-y-6 p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <flux:heading size="xl" class="text-zinc-800 dark:text-white">Kategori Keuangan</flux:heading>
            <flux:subheading size="lg" class="text-zinc-600 dark:text-zinc-400">Kelola kategori Keuangan Mahasiswa</flux:subheading>
        </div>
        
        <flux:modal.trigger name="create-category">
            <flux:button variant="primary" icon="plus" color="emerald">Tambah Kategori</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:separator variant="subtle" />

    <livewire:category.create />
    <livewire:category.edit />

    <div class="flex flex-col md:flex-row gap-3">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari kategori..." icon="magnifying-glass" />
        </div>
        <div class="w-full md:w-48">
            <flux:select wire:model.live="filterType" placeholder="Semua Tipe">
                <flux:select.option value="">Semua Tipe</flux:select.option>
                <flux:select.option value="expense">Pengeluaran</flux:select.option>
                <flux:select.option value="income">Pemasukan</flux:select.option>
            </flux:select>
        </div>
    </div>

    <div class="overflow-x-auto bg-white dark:bg-zinc-900 rounded-lg shadow-sm border border-zinc-200 dark:border-zinc-800">
        <flux:table :paginate="$this->categories">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'id'" :direction="$sortDirection" wire:click="sort('id')">ID</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Nama Kategori</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'type'" :direction="$sortDirection" wire:click="sort('type')">Tipe</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->categories as $category)
                    <flux:table.row :key="$category->id">
                        <flux:table.cell class="text-zinc-500">{{ $category->id }}</flux:table.cell>
                        <flux:table.cell class="font-medium text-zinc-900 dark:text-white">{{ $category->name }}</flux:table.cell>
                        <flux:table.cell>
                            @if($category->type === 'expense')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Pengeluaran</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Pemasukan</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"></flux:button>
                                <flux:menu>
                                    <flux:menu.item icon="pencil" wire:click="edit({{ $category->id }})">Ubah</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item variant="danger" icon="trash" wire:click="$dispatch('confirm-delete', {id: {{ $category->id }}})">Hapus</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        
    </div>
</div>