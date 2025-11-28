<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketActividad extends Model
{
    protected $table = 'ticket_actividad';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'mensaje',
    ];

    public $timestamps = true;

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
