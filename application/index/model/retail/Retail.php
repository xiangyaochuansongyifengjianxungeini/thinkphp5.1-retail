<?php

namespace app\index\model\retail;

use app\index\model\AgentRank;
use app\index\model\BalanceRecord;
use app\index\model\CashRecord;
use app\index\model\CommissionRank;
use app\index\model\CoinRecord;
use app\index\model\Order;
use app\index\model\Shop;
use app\index\model\System;
use app\index\model\User;
use think\Db;
use think\helper\Time;

trait Retail
{
     /**
     * 生成冻结数额
     */
    public function freeze($order_id)
    {
        $order = $this::get($order_id);
        $profits = $order->price-$order->cost;
        $system = System::find();

        $commissionRank = new CommissionRank();
        $commissionRate = $commissionRank->commissionRate();
        Db::transaction(function() use($order,$commissionRate,$system,$profits){
            //获得佣金
            $this->getComission($order->user_id,$order->id,$profits,$commissionRate,0,$system);
            //获得红包
            $this->getPacket($profits,$system,$order->user_id,$order->id);
            //获得资产
            $this->getProperty($profits,$system,$order->user_id,$order->id);
        });
     
    }


    /**
     * 获得佣金
     */
    public function getComission($user_id,$order_id,$price,$rate,$rank,$system)
    {        
        $this->coinRecordStore($price*$rate[$rank],$user_id,$order_id,0,1);

        $user = User::get($user_id);
        $invite_count = User::where('invite_id',$user->invite_id)->count();

        //判断下级用户数量，是否有上级，等级数量
        ++$rank;
        $invite_count < $rank && $price=0;
        isset($user) && $user->invite_id && $rank<=$system->retail_rank && $this->getComission($user->invite_id,$order_id,$price,$rate,$rank,$system);
    }

    /**
     * 获得红包
     * 
     */
    public function getPacket($price,$system,$user_id,$order_id)
    {
        $packet = $price* $system->leverage_rate;

        $this->coinRecordStore($packet,$user_id,$order_id,0,2);
    }

    /**
     * 获得资产
     */
    public function getProperty($price,$system,$user_id,$order_id)
    {
        $amount = $price* $system->leverage_rate;

        $this->coinRecordStore($amount,$user_id,$order_id,0,4);
    }

    /**
     * 获得余额
     */
    public function getBalance($user_id)
    {
        list($start, $end) = Time::today();
        $record = BalanceRecord::where(['user_id'=>$user_id])->where('created_at','between',[$start,$end])->find();

        if($record) return $record;

        $packet = $this->getCoinSum(1,2,$user_id);
        $system = System::find();
        $amount = $packet*$system->packet_rate;
        $amount<$system->packet_min && $amount=$packet;

        Db::transaction(function() use($amount,$user_id){
            $this->balanceRecordStore($amount,$user_id,0);
            $this->coinRecordStore(-$amount,$user_id,0,1,2);
        });

        return BalanceRecord::where(['user_id'=>$user_id])->where('created_at','between',[$start,$end])->find();
    }

    /**
     * 统计红包
     * $status 0冻结 1非冻结
     * $category 1佣金 2红包 3资产
     */
    public function getCoinSum($status,$category,$user_id)
    {
        $amount = CoinRecord::where('status','in',$status)->where(['category'=>$category,'user_id'=>$user_id])->sum('amount');
        //当非冻结佣金大于资产时，佣金等于资产
        if($category == 1 && $status = 1){
            $propetry_amount = CoinRecord::where('status','in',1)->where(['category'=>3,'user_id'=>$user_id])->sum('amount');
            $amount > $propetry_amount && $amount = $propetry_amount;
        } 
        return $amount;
    }

        /**
     * 统计红包
     * $status 0冻结 1非冻结
     * $category 1佣金 2红包 3资产
     */
    public function getCoinRecord($status,$category,$user_id)
    {
        return CoinRecord::where('status','in',$status)->where(['category'=>$category,'user_id'=>$user_id])->paginate(input('post.pageSize',10));

    }

    /**
     * 领取余额
     */
    public function receiveBalance($user_id)
    {
        list($start, $end) = Time::today();
        return BalanceRecord::where(['user_id'=>$user_id])->where('created_at','between',[$start,$end])->update(['status'=>1]);
    }


    /**
     * 新增1佣金 2红包 3资产记录
     */
    public function coinRecordStore($amount,$user_id,$order_id,$status,$category)
    {
        $data = [
            'amount' => $amount,
            'user_id' => $user_id,
            'order_id' => $order_id,
            'status' => $status,
            'category' => $category,
            'created_at' => time(),
        ];
    
        return $amount && CoinRecord::insert($data);
    }

    /**
     * 新增余额记录
     */
    public function balanceRecordStore($amount,$user_id,$status)
    {
        $data = [
            'amount' => $amount,
            'user_id' => $user_id,
            'status' => $status,
            'created_at' => time(),
        ];

        return $amount && BalanceRecord::create($data);
    }

    /**
     * 新增现金记录
     */
    public function cashRecordStore($amount,$user_id)
    {
        $data = [
            'amount' => $amount,
            'user_id' => $user_id,
            'created_at' => time(),
        ];
        return $amount && CashRecord::create($data);
    }


    /**
     * 解冻数额
     */
    public function unfreeze($order_id)
    {
        CoinRecord::where('order_id',$order_id)->update(['status'=>1]);
    }

    /**
     * 订单分润
     */
    public function orderBenefit($order_id)
    {
        $order_data = Order::get($order_id);
        $shop = Shop::get($order_data->shop_id);
        $agentRanks = AgentRank::select();
        $bonus = $order_data->price*$shop->platform_rate-$order_data->offset_balance;

        $agentRank = [];
        foreach($agentRanks as $rank){
            $agentRank[$rank['id']] = $rank['rate'];
        }
 
        Db::transaction(function() use($bonus,$shop,$agentRank){
            $this->getBenefit($bonus,$shop->agent_id,$agentRank,0);
        });
        
    }


    /**
     * 获取每个代理分润
     */
    public function getBenefit($price,$agent_id,$agentRank,$rank)
    {
        $user = User::get($agent_id);
        
        $rank == 0 && $user->agent_rank_id != 1 && $this->cashRecordStore($price*$agentRank[1],$agent_id);

        $amount = $price*$agentRank[$user->agent_rank_id];
        $this->cashRecordStore($amount,$agent_id);

        ++$rank && $user->agent_id && $this->getBenefit($price,$user->agent_id,$agentRank,$rank);
    }
}