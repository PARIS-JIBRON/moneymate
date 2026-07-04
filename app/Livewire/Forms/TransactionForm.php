<?php

namespace App\Livewire\Forms;

use App\Models\Transaction;
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

    public function store()
    {
        $this->validate();
        // lakukan validasi mengecek anggaran yang sesuai


        Transaction::create([
            'user_id' => Auth::id(),
            'category_id' => $this->category_id,
            'amount' => $this->amount,
            'type' => $this->type,
            'description' => $this->description,
            'transaction_date' => $this->transaction_date,
        ]);

        // kurangin anggaran yang sesuai dengan kategori dan tipe transaksi
        

        $this->reset(['category_id', 'amount', 'description']);
    }

    public function update()
    {
        $this->validate();

        $this->transaction->update([
            'category_id' => $this->category_id,
            'amount' => $this->amount,
            'type' => $this->type,
            'description' => $this->description,
            'transaction_date' => $this->transaction_date,
        ]);
    }
}