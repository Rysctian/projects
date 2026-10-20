<?php

namespace App\Models;

use Database\Factories\ProjectLabelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectLabel extends Model
{
    /** @use HasFactory<ProjectLabelFactory> */
    use HasFactory;

    protected $guarded = ['id'];
}
