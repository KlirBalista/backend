<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class PatientBill extends Model
{
    use HasFactory;

    protected $fillable = [
        'birthcare_id',
        'patient_id',
        'bill_number',
        'bill_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'balance_amount',
        'status',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'bill_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
    ];

    // Relationships
    public function birthcare(): BelongsTo
    {
        return $this->belongsTo(BirthCare::class, 'birthcare_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class, 'patient_bill_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BillPayment::class, 'patient_bill_id');
    }

    // Accessors & Mutators
    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date->isPast() && $this->balance_amount > 0;
    }

    public function getStatusBadgeAttribute(): array
    {
        $badges = [
            'draft' => ['class' => 'bg-gray-100 text-gray-800', 'text' => 'Draft'],
            'sent' => ['class' => 'bg-blue-100 text-blue-800', 'text' => 'Sent'],
            'partially_paid' => ['class' => 'bg-yellow-100 text-yellow-800', 'text' => 'Partially Paid'],
            'paid' => ['class' => 'bg-green-100 text-green-800', 'text' => 'Paid'],
            'overdue' => ['class' => 'bg-red-100 text-red-800', 'text' => 'Overdue'],
            'cancelled' => ['class' => 'bg-gray-100 text-gray-800', 'text' => 'Cancelled'],
        ];

        return $badges[$this->status] ?? $badges['draft'];
    }

    // Business Logic Methods
    public function calculateTotals(): void
    {
        $this->subtotal = $this->items->sum('total_price');
        $this->total_amount = $this->subtotal + $this->tax_amount - $this->discount_amount;
        $this->balance_amount = $this->total_amount - $this->paid_amount;
    }

    public function updatePaymentStatus(): void
    {
        $this->paid_amount = $this->payments->sum('amount');
        $this->balance_amount = $this->total_amount - $this->paid_amount;

        if ($this->balance_amount <= 0) {
            $this->status = 'paid';
        } elseif ($this->paid_amount > 0) {
            $this->status = 'partially_paid';
        } elseif ($this->is_overdue) {
            $this->status = 'overdue';
        } else {
            // Keep existing status if none of the above conditions are met
            // This preserves 'draft' or 'sent' status when no payments have been made
        }

        $this->save();
    }

    public static function generateBillNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $lastBill = self::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastBill ? (int) substr($lastBill->bill_number, -4) + 1 : 1;

        return sprintf('BILL-%s%s-%04d', $year, $month, $sequence);
    }

    // Scopes
    public function scopeUnpaid($query)
    {
        return $query->where('balance_amount', '>', 0)
                    ->whereIn('status', ['sent', 'partially_paid', 'overdue']);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
                    ->where('balance_amount', '>', 0);
    }

    public function scopeForBirthcare($query, $birthcareId)
    {
        return $query->where('birthcare_id', $birthcareId);
    }
}
