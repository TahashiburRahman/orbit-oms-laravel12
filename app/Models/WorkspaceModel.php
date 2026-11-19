<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Model;

abstract class WorkspaceModel extends Model
{
    use Audited;

    protected $guarded = [];
}
