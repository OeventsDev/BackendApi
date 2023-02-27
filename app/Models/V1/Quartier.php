<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @method static whereId($id)
 */
class Quartier extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        'name',
        'ville_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    public function ville(){
        return $this->belongsTo(Ville::class)->with('region');
    }
}
