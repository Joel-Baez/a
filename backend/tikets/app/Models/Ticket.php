<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $table = 'tickets';

    protected $fillable = [
        'title',
        'description',
        'status',
        'creator_id',
        'assigned_admin_id',
    ];

    protected $attributes = [
        'status' => 'abierto',
    ];

    public $timestamps = true;

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function assignedAdmin()
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function comments()
    {
        return $this->hasMany(TicketComment::class)->orderBy('created_at', 'asc');
    }
}
