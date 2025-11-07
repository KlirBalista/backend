<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class BirthCareDocument extends Model
{
    protected $fillable = ['birth_care_id', 'document_type', 'document_path', 'timestamp'];

    public function birthCare()
    {
        return $this->belongsTo(BirthCare::class);
    }

    public function getFileUrl(): string
    {
        // Try Supabase birthcare bucket first
        if (Storage::disk('supabase_birthcare')->exists($this->document_path)) {
            $adapter = Storage::disk('supabase_birthcare')->getAdapter();
            if (method_exists($adapter, 'publicUrl')) {
                return $adapter->publicUrl($this->document_path);
            }
        }
        // Fall back to public storage
        return Storage::disk('public')->url($this->document_path);
    }
}
