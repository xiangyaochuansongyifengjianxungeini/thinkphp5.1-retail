<?php

namespace app\index\model;

use think\Model;
use app\index\model\retail\Retail;

class Order extends Model
{
    use Retail;

    public function user()
    {
        return $this->belongsTo('app\index\model\User');
    }
}