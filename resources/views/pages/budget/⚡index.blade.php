<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use App\Models\Budget;

new class extends Component
{
    use WithPagination;

    public $sortBy = 'year';
    public $sortDirection = 'desc';
    public $filterYear = '';

    public function mount()
    {
        $this->filterYear = date('Y');
    }

    public function sort($column) {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    #[Computed]
    public function budgets()
    {
        return Budget::query()
            ->with('category')
            ->where('user_id', auth()->id())
            ->when($this->filterYear, fn($q) => $q->where('year', $this->filterYear))
            ->orderBy($this->sortBy, $this->sortDirection)
            ->orderBy('month', 'desc')
            ->paginate(10);
    }

    public function edit($id)
    {
        $this->dispatch('edit-budget', id: $id);
    }
};
?>

<div class="min-h-screen bg-zinc-50 dark:bg-zinc-950">
    <nav class="bg-white dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center gap-8">
                    <div class="flex-shrink-0 flex items-center gap-2">
                        <flux:icon name="currency-dollar" class="text-emerald-600 w-6 h-6" />
                        <span class="font-bold text-xl text-zinc-900 dark:text-white">MoneyMate</span>
                    </div>
                    <div class="hidden sm:flex sm:space-x-4">
                        <flux:navbar.item :href="route('transaction.index')" :current="request()->routeIs('transaction.index')" wire:navigate>Transaksi</flux:navbar.item>
                        <flux:navbar.item :href="route('category.index')" :current="request()->routeIs('category.index')" wire:navigate>Kategori</flux:navbar.item>
                        <flux:navbar.item :href="route('budget.index')" :current="request()->routeIs('budget.index')" wire:navigate>Anggaran</flux:navbar.item>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto space-y-4 p-4 sm:p-6 lg:p-8 bg-green-100 dark:bg-zinc-800">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <flux:heading size="xl" class="text-zinc-800 dark:text-white">Anggaran Bulanan (Budgets)</flux:heading>
                <flux:subheading size="lg" class="text-zinc-600 dark:text-zinc-400">Batasi pengeluaran kategori bulanan Anda</flux:subheading>
            </div>

            <flux:modal.trigger name="create-budget">
                <flux:button variant="primary" icon="plus" color="emerald">Tambah Anggaran</flux:button>
            </flux:modal.trigger>
        </div>

        <flux:separator variant="subtle" />

        <livewire:budget.create />
        <livewire:budget.edit />

        <div class="flex gap-3">
            <div class="w-full sm:w-48">
                <flux:select wire:model.live="filterYear" placeholder="Pilih Tahun">
                    <flux:select.option value="">Semua Tahun</flux:select.option>
                    @foreach(range(date('Y') - 2, date('Y') + 2) as $year)
                        <flux:select.option value="{{ $year }}">{{ $year }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <div class="overflow-x-auto bg-white dark:bg-zinc-900 rounded-lg shadow-sm border border-zinc-200 dark:border-zinc-800">
            <flux:table :paginate="$this->budgets">
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortBy === 'year'" :direction="$sortDirection" wire:click="sort('year')">Periode</flux:table.column>
                    <flux:table.column>Kategori</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'limit_amount'" :direction="$sortDirection" wire:click="sort('limit_amount')">Batas Anggaran</flux:table.column>
                    <flux:table.column>Terpakai</flux:table.column>
                    <flux:table.column>Sisa</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->budgets as $budget)
                        @php
                            $used = $budget->usedAmount();
                            $remaining = $budget->remainingAmount();
                            $statusClass = $remaining < 0 ? 'text-red-600' : 'text-emerald-600';
                        @endphp
                        <flux:table.row :key="$budget->id">
                            <flux:table.cell class="whitespace-nowrap font-medium text-zinc-900 dark:text-white">
                                {{ DateTime::createFromFormat('!m', $budget->month)->format('F') }} {{ $budget->year }}
                            </flux:table.cell>

                            <flux:table.cell>
                                {{ $budget->category->name }}
                            </flux:table.cell>

                            <flux:table.cell class="font-bold text-zinc-900 dark:text-white">
                                Rp {{ number_format($budget->limit_amount, 2, ',', '.') }}
                            </flux:table.cell>

                            <flux:table.cell class="text-zinc-900 dark:text-white">
                                Rp {{ number_format($used, 2, ',', '.') }}
                            </flux:table.cell>

                            <flux:table.cell class="{{ $statusClass }} font-semibold">
                                Rp {{ number_format($remaining, 2, ',', '.') }}
                            </flux:table.cell>

                            <flux:table.cell>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $remaining < 0 ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400' }}">
                                    {{ $remaining < 0 ? 'Over Budget' : 'Aman' }}
                                </span>
                            </flux:table.cell>

                            <flux:table.cell>
                                <flux:dropdown>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"></flux:button>
                                    <flux:menu>
                                        <flux:menu.item icon="pencil" wire:click="edit({{ $budget->id }})">Edit</flux:menu.item>
                                        <flux:menu.separator />
                                        <flux:menu.item variant="danger" icon="trash" wire:click="$dispatch('confirm-delete', {id: {{ $budget->id }}})">Delete</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    </main>
</div>