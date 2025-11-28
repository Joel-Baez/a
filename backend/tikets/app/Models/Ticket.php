<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $table = 'tickets';

    protected $fillable = [
        'titulo',
        'descripcion',
        'estado',
        'gestor_id',
        'admin_id',
    ];

    protected $attributes = [
        'estado' => 'abierto',
    ];

    public $timestamps = true;

    public function gestor()
    {
        return $this->belongsTo(User::class, 'gestor_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function actividades()
    {
        return $this->hasMany(TicketActividad::class, 'ticket_id')->orderBy('created_at', 'asc');
    }
}
