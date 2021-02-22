<?php


namespace app\api\controller\v1;

use app\api\model\Category as CategoryModel;
use app\lib\exception\CategoryException;

class Category
{

    public function getAllCategories(){
        $categories = CategoryModel::with('img')->where('pid',0)->select();
        if($categories->isEmpty()){
            throw new CategoryException();
        }
        foreach ($categories as $item) {
            $item['children'] = CategoryModel::with('img')->where('pid',$item['id'])->select();
        }
        $categories->hidden(['']);
        return $categories;
    }

}
