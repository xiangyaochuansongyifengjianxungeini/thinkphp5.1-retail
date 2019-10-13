<?php
namespace app\index\controller;

use app\index\model\BalanceRecord;
use app\index\model\CashRecord;
use app\index\model\User;
use app\index\model\Order;
use app\index\model\Shop;
use app\index\model\System;
use think\Db;
use think\Request;

class Index
{
    public function index()
    {
        return '<style type="text/css">*{ padding: 0; margin: 0; } div{ padding: 4px 48px;} a{color:#2E5CD5;cursor: pointer;text-decoration: none} a:hover{text-decoration:underline; } body{ background: #fff; font-family: "Century Gothic","Microsoft yahei"; color: #333;font-size:18px;} h1{ font-size: 100px; font-weight: normal; margin-bottom: 12px; } p{ line-height: 1.6em; font-size: 42px }</style><div style="padding: 24px 48px;"> <h1>:) </h1><p> ThinkPHP V5.1<br/><span style="font-size:30px">12载初心不改（2006-2018） - 你值得信赖的PHP框架</span></p></div><script type="text/javascript" src="https://tajs.qq.com/stats?sId=64890268" charset="UTF-8"></script><script type="text/javascript" src="https://e.topthink.com/Public/static/client.js"></script><think id="eab4b9f840753f8e7"></think>';
    }

    public function hello($name = 'ThinkPHP5')
    {
        return 'hello,' . $name;
    }

    /**
     * 绑定用户
     */
    public function bindUser(User $user)
    {
        $user_id = input('post.user_id');
        $invite_code = input('post.invite_code');
        $data['invite_id'] = decode($invite_code);
 
        $result = $user->where('id',$user_id)->update($data);
        if($result){
            return result('成功','',1);
        }
        return $result('失败','',0);

    }

    /**
     * 生成冻结数额
     */
    public function freeze(Order $order)
    {
        $order_id = input('post.order_id');
        $order->freeze($order_id);
    }

    /**
     * 解冻数额
     */
    public function unfreeze(Order $order)
    {
        $order_id = input('post.order_id');
        $order->unfreeze($order_id);
    }

    /**
     * 获取余额 
     */
    public function getBalance(Order $order)
    {
        $user_id = input('post.user_id');

        $banlance = $order->getBalance($user_id);

        return result('成功',$banlance,1);
    }


    /**
     * 新人红包
     */
    public function newUserPacket(Order $order)
    {
        $user_id = input('post.user_id');
        $user = User::get($user_id);

        if(!$user || !$user->is_new){
           return result('用户无领取资格','',0);
        }

        $stytem = System::find();
        return Db::transaction(function() use($stytem,$order,$user_id,$user){
            $result = $order->coinRecordStore($stytem->new_user_packet,$user_id,0,1,2);
            $user->save(['is_new'=>0]);

            if($result){
                return result('成功',$result,1);
            }
            return result('失败',$result,0);
        });
       
    }

    /**
     * 领取余额
     */
    public function receiveBalance(Order $order)
    {
        $user_id = input('post.user_id');

        $banlance = $order->receiveBalance($user_id);

        return result('成功',$banlance,1);
    }

    /**
     * 获取用户钱包详情
     */
    public function walletDetail(Order $order)
    {
        $user_id = input('post.user_id');

        $result['cash'] = CashRecord::where('user_id',$user_id)->sum('amount');
        $result['balance'] = BalanceRecord::where('user_id',$user_id)->sum('amount');
        $result['packet'] = $order->getCoinSum('1',2,$user_id);
        $result['comission'] = $order->getCoinSum('1',1,$user_id);
        $result['property'] = $order->getCoinSum('1',3,$user_id);

        return result('成功',$result,1);
    }

    /**
     * 获取现金详情
     */
    public function cashDetail()
    {
        $user_id = input('post.user_id');

        $cashRecords = CashRecord::where('user_id',$user_id)->paginate(input('post.pageSize',10));

        return result('成功',$cashRecords,1);
    }

    /**
     * 获取余额详情
     */
    public function balanceDetail()
    {
        $user_id = input('post.user_id');

        $balanceRecords = BalanceRecord::where('user_id',$user_id)->paginate(input('post.pageSize',10));


        return result('成功',$balanceRecords,1);
    }

    /**
     * 获取红包、佣金、资产详情
     * $type 1佣金 2红包 3资产
     */
    public function coinDetail(Order $order)
    {
        $user_id = input('post.user_id');
        $type = input('post.type');

        $result['freeze'] = $order->getCoinSum('0',$type,$user_id);
        $result['unfreeze'] = $order->getCoinSum('1',$type,$user_id);
        $result['record'] = $order->getCoinRecord('1',$type,$user_id);

        return result('成功',$result,1);
    }


    /**
     * 获取换算金额
     */
    public function replaceBalance()
    {
        $shop_id = input('post.shop_id');
        $price = input('post.price');

        $shop = Shop::get($shop_id);
 
        $result['max_balance'] = round($price*$shop->platform_rate*$shop->balance_rate,2);
        $result['min_cash'] = $price-$result['max_balance'];

        return result('成功',$result,1);
    }

    /**
     * 订单分润
     */
    public function orderBenefit(Order $order)
    {
        $order_id = input('post.order_id');

        $order->orderBenefit($order_id);

    }

    /**
     * 碰撞生成现金
     */
    public function cashCreate(Order $order)
    {
        $commission = input('post.commission');
        $user_id = input('post.user_id');

        $user_commission = $order->getCoinSum('1',1,$user_id);
        if($commission > $user_commission) return result('佣金不足','',0);
        
        $system = System::find();
        if($commission<$system->min_crash_commission) return result('需要最低佣金值为'.$system->min_crash_commission,'',0);

        return Db::transaction(function() use($order,$commission,$user_id){
           $order->coinRecordStore(-$commission,$user_id,0,1,1);
           $order->balanceRecordStore(-$commission,$user_id,1);
           $order->cashRecordStore($commission,$user_id);

           return result('成功','',1);
        });
        
    } 

}
