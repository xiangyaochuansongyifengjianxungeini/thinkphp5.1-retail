<?php

namespace app\index\model;

use think\Model;

class CommissionRank extends Model
{
    public function commissionRate()
    {
        $CommissionRanks = $this->select();
        
        foreach($CommissionRanks as $rank){
            $CommissionRank[$rank['rank']] = $rank['rate'];
        }

        return $CommissionRank;
    }
}