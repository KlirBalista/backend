<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PatientDocument extends Model
{
    protected $fillable = [
        'patient_id',
        'birth_care_id',
        'title',
        'file_name',
        'file_path',
        'document_type',
        'file_size',
        'mime_type',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected $appends = ['file_url'];

    // Relationships
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function birthCare(): BelongsTo
    {
        return $this->belongsTo(BirthCare::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Accessor for file_url attribute
    public function getFileUrlAttribute(): string
    {
        return $this->getFileUrl();
    }

    // Helper methods
    public function getFileUrl(): string
    {
        // Try Supabase patient bucket first
        if (Storage::disk('supabase_patient')->exists($this->file_path)) {
            $adapter = Storage::disk('supabase_patient')->getAdapter();
            if (method_exists($adapter, 'publicUrl')) {
                return $adapter->publicUrl($this->file_path);
            }
        }
        // Fall back to public storage
        return Storage::disk('public')->url($this->file_path);
    }

    public function getFormattedFileSize(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    // Delete file when model is deleted
    protected static function boot()
    {
        parent::boot();
        
        static::deleting(function ($document) {
            if ($document->file_path) {
                // Try to delete from Supabase patient bucket, public, and private storage
                if (Storage::disk('supabase_patient')->exists($document->file_path)) {
                    Storage::disk('supabase_patient')->delete($document->file_path);
                } elseif (Storage::disk('public')->exists($document->file_path)) {
                    Storage::disk('public')->delete($document->file_path);
                } elseif (Storage::exists($document->file_path)) {
                    Storage::delete($document->file_path);
                }
            }
        });
    }
}
