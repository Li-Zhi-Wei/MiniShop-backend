<?php


namespace app\api\service;


use app\api\model\OrderProduct;
use app\api\model\Product;
use app\api\model\UserAddress;
use app\api\model\Order as OrderModel;
use app\api\model\Postage as PostageModel;
use app\lib\enum\OrderStatusEnum;
use app\lib\exception\OrderException;
use app\lib\exception\UserException;
use think\Db;
use think\Exception;

class Order
{
    //订单的商品列表，也就是客户端传递过来的products参数
    protected $oProducts;
    //从数据库中取出的真实的商品信息（包括库存量）
    protected $products;

    protected $uid;

    /**
     * 创建订单
     * @param $uid
     * @param $oProducts
     * @return array
     * @throws Exception
     * @throws OrderException
     * @throws UserException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function place($uid,$oProducts){
        //oProducts和products作对比，检测库存量
        $this->oProducts = $oProducts;
        $this->products = $this->getProductsByOrder($oProducts);
        $this->uid = $uid;
        $status = $this->getOrderStatus();
        if(!$status['pass']){
            $status['order_id'] = -1;
            return $status;
        }
        //开始创建订单
        $orderSnap = $this->snapOrder($status);
        $order = $this->createOrder($orderSnap);
        $order['pass'] = true;
        return $order;
    }

    /**
     * 保存订单快照到数据库
     * @param $snap
     */
    private function createOrder($snap){
        Db::startTrans();
        try{
            $orderNo = $this->makeOrderNo();
            $order = new OrderModel();
            $order->user_id = $this->uid;
            $order->order_no = $orderNo;
            $order->total_price = $snap['orderPrice'];
            $order->total_count = $snap['totalCount'];
            $order->snap_img = $snap['snapImg'];
            $order->snap_name = $snap['snapName'];
            $order->snap_address = $snap['snapAddress'];
            $order->snap_items = json_encode($snap['pStatus']);
            $order->postage_price = $snap['postagePrice'];
            $order->save();

            $orderID = $order->id;
            $create_time = $order->create_time;
            foreach ($this->oProducts as &$p) {
                $p['order_id'] = $orderID;
            }
            $orderProduct = new OrderProduct();
            $orderProduct->saveAll($this->oProducts);
            Db::commit();
            return [
                'order_no' => $orderNo,
                'order_id' => $orderID,
                'create_time' => $create_time,
            ];
        }
        catch (Exception $ex){
            Db::rollback();
            throw $ex;
        }

    }

    /**
     * 生成订单号
     * @return string
     */
    public static function makeOrderNo(){
        $yCode = array('A','B','C','D','E','F','G','H','I','J');
        $orderSn = $yCode[intval(date('Y'))-2019].strtoupper(dechex(date('m'))).
            date('d').substr(time(),-5).substr(microtime(),2,5).
            sprintf('%02d',rand(0,99));
        return $orderSn;
    }

    /**
     * 生成订单快照
     * @param $status
     * @return array
     * @throws UserException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function snapOrder($status){
        $snap = [
            'orderPrice' => 0,
            'totalCount' => 0,
            'pStatus' => [],
            'snapAddress' => null,
            'snapName' => '',
            'snapImg' => '',
            'postagePrice' => 0,
        ];

        $snap['orderPrice'] = $status['orderPrice'];
        $snap['totalCount'] = $status['totalCount'];
        $snap['pStatus'] = $status['pStatusArray'];
        $snap['snapAddress'] = json_encode($this->getUserAddress());
        $snap['snapName'] = $this->products[0]['name'];
        $snap['snapImg'] = $this->products[0]['main_img_url'];
        $snap['postagePrice'] = $status['postagePrice'];

        if(count($this->products)>1){
            $snap['snapName'] .= '等'.$status['totalCount'].'件商品';
        }
        return $snap;
    }

    /**
     * 获取用户收货地址
     * @return array
     * @throws UserException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function getUserAddress(){
        $userAddress = UserAddress::where('user_id','=',$this->uid)->find();
        if(!$userAddress){
            throw new UserException([
                'msg' => '用户收货地址不存在，下单失败',
                'errorCode' => 60001,
            ]);
        }
        return $userAddress->toArray();
    }

    /**
     * 获取订单状态
     * @return array
     * @throws OrderException
     */
    private function getOrderStatus(){
        $status = [
            'pass' => true,
            'orderPrice' => 0,
            'pStatusArray' => [],//保存订单内所有商品的详细信息
            'totalCount' => 0,
            'postagePrice' => 0,
        ];
        foreach ($this->oProducts as $oProduct){
            $pStatus = $this->getProductStatus(
                $oProduct['product_id'],$oProduct['count'],$this->products
            );
            if(!$pStatus['haveStock']){
                $status['pass'] = false;
            }
            $status['orderPrice'] += $pStatus['totalPrice'];
            $status['totalCount'] += $pStatus['counts'];
            array_push($status['pStatusArray'],$pStatus);
        }
        $postage = PostageModel::find(1);
        //检测是否包邮
        if($status['orderPrice'] < $postage->condition){
            $status['orderPrice'] += $postage->price;
            $status['postagePrice'] = $postage->price;
        }
        return $status;
    }

    /**
     * 获得当前商品的状态
     * @param $oPID
     * @param $oCount
     * @param $products
     * @return array
     * @throws OrderException
     */
    private function getProductStatus($oPID,$oCount,$products){
        //当前商品在products数组里的序号
        $pIndex = -1;
        //定义某一个商品的详细信息
        $pStatus = [
            'id' => null,
            'haveStock' => false,
            'counts' => 0,
            'price' => 0,
            'name' => '',
            'totalPrice' => 0,
            'main_img_url' => null
        ];
        for($i=0;$i<count($products);$i++){
            if($oPID == $products[$i]['id']){
                $pIndex = $i;
            }
        }
        if($pIndex == -1){
            //客户端传递的product_id可能根本不存在
            throw new OrderException([
                'msg' => 'id为'.$oPID.'的商品不存在，创建订单失败'
            ]);
        }
        else{
            $product = $products[$pIndex];
            $pStatus['id'] = $product['id'];
            $pStatus['counts'] = $oCount;
            $pStatus['name'] = $product['name'];
            $pStatus['totalPrice'] = $product['price']*$oCount;
            $pStatus['price'] = $product['price'];
            $pStatus['main_img_url'] = $product['main_img_url'];
            if($product['stock']-$oCount >= 0){
                $pStatus['haveStock'] = true;
            }
        }
        return $pStatus;
    }

    /**
     * 根据订单信息查找真实的商品信息
     * @param $oProducts
     * @return mixed
     */
    private function getProductsByOrder($oProducts){
        $oPIDs = [];//商品id号
        foreach ($oProducts as $item){
            array_push($oPIDs,$item['product_id']);
        }
        $products = Product::all($oPIDs)
            ->visible(['id','price','stock','name','main_img_url'])
            ->toArray();
        return $products;
    }

    /**
     * 根据订单ID检测库存量
     * @param $orderID
     * @return array
     * @throws OrderException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function checkOrderStock($orderID){
        $oProducts = OrderProduct::where('order_id','=',$orderID)->select();
        $this->oProducts = $oProducts;
        $this->products = $this->getProductsByOrder($oProducts);
        $status = $this->getOrderStatus();
        return $status;
    }

    /**
     * 发货
     * @param $orderID
     * @param string $jumpPage
     * @return bool
     * @throws OrderException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function delivery($orderID,$expressNumber)
    {
        $order = OrderModel::where('id', '=', $orderID)->find();
        if (!$order) {
            throw new OrderException();
        }
        if ($order->status != OrderStatusEnum::PAID) {
            throw new OrderException([
                'msg' => '还没付款呢，想干嘛？或者你已经更新过订单了，不要再刷了',
                'errorCode' => 80002,
                'code' => 403
            ]);
        }
        Db::startTrans();
        try {
            $order->status = OrderStatusEnum::DELIVERED;
            $order->express_number = $expressNumber;
            $order->save();
            $message = new DeliveryMessage();
            $result = $message->sendDeliveryMessage($order);
            Db::commit();
            return $result;
        }
        catch (Exception $ex){
                Db::rollback();
                throw $ex;
        }
    }
}