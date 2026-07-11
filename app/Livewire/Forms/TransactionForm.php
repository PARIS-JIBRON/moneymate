<?php

namespace App\Livewire\Forms;

use App\Models\Budget;
use App\Models\Transaction;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\Auth;
use Livewire\Form;

class TransactionForm extends Form
{
    public ?Transaction $transaction = null;

    public string $category_id = '';
    public string $amount = '';
    public string $type = 'expense'; // Default pengeluaran mahasiswa
    public string $description = '';
    public string $transaction_date = '';

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:categories,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'type' => ['required', 'string', 'in:income,expense'],
            'description' => ['nullable', 'string', 'max:1000'],
            'transaction_date' => ['required', 'date'],
        ];
    }

    public function setTransaction(Transaction $transaction): void
    {
        $this->transaction = $transaction;
        $this->category_id = $transaction->category_id;
        $this->amount = $transaction->amount;
        $this->type = $transaction->type;
        $this->description = $transaction->description ?? '';
        $this->transaction_date = $transaction->transaction_date->format('Y-m-d\TH:i');
    }

    protected function getMatchingBudget(): ?Budget
    {
        if ($this->type !== 'expense') {
            return null;
        }

        $date = Carbon::parse($this->transaction_date);

        return Budget::query()
            ->where('user_id', Auth::id())
            ->where('category_id', $this->category_id)
            ->where('month', $date->month)
            ->where('year', $date->year)
            ->first();
    }

    protected function notifyBudgetStatus(Budget $budget): void
    {
        if ($budget->isOverBudget()) {
            $monthName = DateTime::createFromFormat('!m', $budget->month)->format('F');
            \Flux::toast(
                variant: 'warning',
                text: "Pengeluaran untuk {$budget->category->name} {$monthName} {$budget->year} telah melebihi batas anggaran."
            );
        }
    }

    public function store()
    {
        $this->validate();

        $budget = $this->getMatchingBudget();

        Transaction::create([
            'user_id' => Auth::id(),
            'category_id' => $this->category_id,
            'budget_id' => $budget?->id,
            'amount' => $this->amount,
            'type' => $this->type,
            'description' => $this->description,
            'transaction_date' => $this->transaction_date,
        ]);

        if ($budget) {
            $this->notifyBudgetStatus($budget);
        }

        $this->reset(['category_id', 'amount', 'description']);
    }

    public function update()
    {
        $this->validate();

        $budget = $this->getMatchingBudget();

        $this->transaction->update([
            'category_id' => $this->category_id,
            'budget_id' => $budget?->id,
            'amount' => $this->amount,
            'type' => $this->type,
            'description' => $this->description,
            'transaction_date' => $this->transaction_date,
        ]);

        if ($budget) {
            $this->notifyBudgetStatus($budget);
        }
    }
}