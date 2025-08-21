<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'parent_id'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationship với parent (self-referencing)
    public function parent()
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    // Relationship với children
    public function children()
    {
        return $this->hasMany(Location::class, 'parent_id');
    }

    // Get all descendants (recursive)
    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    // Get full path (City > District > Ward)
    public function getFullPathAttribute()
    {
        $path = collect([$this->name]);
        
        $parent = $this->parent;
        while ($parent) {
            $path->prepend($parent->name);
            $parent = $parent->parent;
        }
        
        return $path->implode(' > ');
    }

    // Scope: get by type
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Scope: get cities (no parent)
    public function scopeCities($query)
    {
        return $query->where('type', 'city')->whereNull('parent_id');
    }

    // Scope: get districts of a city
    public function scopeDistricts($query, $cityId = null)
    {
        $query = $query->where('type', 'district');
        
        if ($cityId) {
            $query->where('parent_id', $cityId);
        }
        
        return $query;
    }

    // Scope: get wards of a district  
    public function scopeWards($query, $districtId = null)
    {
        $query = $query->where('type', 'ward');
        
        if ($districtId) {
            $query->where('parent_id', $districtId);
        }
        
        return $query;
    }
}