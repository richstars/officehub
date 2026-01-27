<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupervisionReportDetail extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function report()
    {
        return $this->belongsTo(SupervisionReport::class, 'supervision_report_id');
    }

    public function airport()
    {
        return $this->belongsTo(Airport::class);
    }

    public function airline()
    {
        return $this->belongsTo(Airline::class);
    }
}
