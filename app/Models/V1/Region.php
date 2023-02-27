<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @method static whereId($id)
 */
class Region extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        'name',
        'pays_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    public function pays(){
        return $this->belongsTo(Pays::class);
    }

    public function villes(){
        return $this->hasMany(Ville::class)->with('quartiers');
    }
}
