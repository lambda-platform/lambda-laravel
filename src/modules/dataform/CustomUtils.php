<?php

namespace Lambda\Dataform;

use Illuminate\Support\Facades\DB;

trait CustomUtils
{

    public function customCallTrigger($action, $data,$subdata=null,$parentID,$status)
    {
        switch ($action) {
            case 'beforeInsertDeleteOld':
                if($data->model=='product_hasalt')
                {
                    $oldbaraas =DB::table('product_hasalt')->where($data->parent, $parentID)->get();
                    if($oldbaraas){
                        foreach($oldbaraas as $oldbaraa){
                            DB::table('products')->where('id',$oldbaraa->baraaid)->increment('counter',$oldbaraa->counter);
                        }}
                }
                break;
            case 'beforeInsert':
                if($data->model=='product_hasalt')
                {

                    $productprev=DB::table('products')->where('id',$data['baraaid'])->first();
                    $sd['customdate']=\Carbon\Carbon::now();
                    DB::table('products')->where('id',$sd['baraaid'])->decrement('counter',$sd['counter']);
                    $productnow=DB::table('products')->where('id',$sd['baraaid'])->first();
                    if($status=='store')
                    {
                        DB::table('products_logs')->insert([
                            'counter_prev'=>$productprev->counter,
                            'counter_now'=>$productnow->counter,
                            'name_prev'=>$productprev->name,
                            'name'=>$productnow->name,
                            'customdate'=>$sd['customdate'],
                            'username'=>Auth()->user()->name,
                            'desc'=> Auth()->user()->name . ':' . $sd['costumerid'] . '-хэрэглэгчид ' . $sd['counter'] . ' ширхэг бараа олгосон'
                        ]);
                    }
                    else if($status=='update'){
                        DB::table('products_logs')->insert([
                            'counter_prev'=>$productprev->counter,
                            'counter_now'=>$productnow->counter,
                            'name_prev'=>$productprev->name,
                            'name'=>$productnow->name,
                            'customdate'=>$sd['customdate'],
                            'username'=>Auth()->user()->name,
                            'desc'=> Auth()->user()->name . ':' . $sd['costumerid'] . '-хэрэглэгчид ' . $sd['counter'] . ' ширхэг бараанд засвар хийсэн'
                        ]);
                    }
                }
                break;

        }
    }

}
