<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketComment extends Model
{
    protected $table = 'ticket_comments';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'comment',
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
