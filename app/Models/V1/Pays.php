<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @method static whereId($id)
 */
class Pays extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        'name',
        'code',
        'indicatif',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function regions(){
        return $this->hasMany(Region::class)->with('villes');
    }
}
