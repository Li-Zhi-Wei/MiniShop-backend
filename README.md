<h1 align="center">
  MiniShop-CMS-backend
</h1>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-%3E%3D7.1-blue.svg" alt="php version" data-canonical-src="https://img.shields.io/badge/PHP-%3E%3D7.1-blue.svg" style="max-width:100%;"></a>
  <a href="https://www.kancloud.cn/manual/thinkphp5_1/353946" rel="nofollow"><img src="https://img.shields.io/badge/ThinkPHP-5.1.*-green.svg" alt="ThinkPHP version" data-canonical-src="https://img.shields.io/badge/ThinkPHP-5.1.*-green.svg" style="max-width:100%;"></a>
  <img src="https://img.shields.io/badge/license-license--2.0-lightgrey.svg" alt="LISENCE" data-canonical-src="https://img.shields.io/badge/license-license--2.0-lightgrey.svg" style="max-width:100%;"></a>
</p>

# 简介

本项目是基于TP5.1框架的微信小程序商城后端程序

配套项目请访问

* [商城后台VUE前端项目](https://github.com/Li-Zhi-Wei/MiniShop-CMS-VUE)
* [商城后台TP5后端项目](https://github.com/Li-Zhi-Wei/MiniShop-CMS-backend)

## 线上 Demo

微信扫码

![UP4RmQ.jpg](https://s1.ax1x.com/2020/07/06/UP4RmQ.jpg)

# 快速开始

* 安装MySQL（version： 5.6+）

* 安装PHP环境(version： 7.1+)

* 安装WEB服务器

* 下载工程项目

## 数据库配置

新建一个数据库，导入DataBase.sql。进入工程根目录下``/config/database.php``，找到如下配置项：

```php
// 服务器地址
  'hostname'        => '',
// 数据库名
  'database'        => '',
// 用户名
  'username'        => 'root',
// 密码
  'password'        => '',
  
//省略后面一堆的配置项
```

## 其他配置

进入工程根目录下``/config/api/``，根据自己的实际情况填写各项配置

# 特别说明

本项目学习自七月大佬的课程，并结合项目需求增加了一些功能（如增加了邮费模块、商品套餐模块、商品设置、用户操作埋点等），后台项目不在课程内，是我自己做的。
