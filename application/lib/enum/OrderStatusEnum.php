<?php


namespace app\lib\enum;


class OrderStatusEnum
{
    //待支付
    const UNPAID = 1;
    //已支付
    const PAID = 2;
    //已发货
    const DELIVERED = 3;
    //已支付，但库存不足
    const PAID_BUT_OUT_OF = 4;
    // 已退款
    const REFUNDED = 5;
    // 已收货
    const RECEIVED = 6;
    // 已关闭
    const CLOSED = 7;
}
