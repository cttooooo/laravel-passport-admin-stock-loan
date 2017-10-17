<?php

namespace App\Http\Model;


class ClientWithdraw extends Base
{
    protected $table = "u_cust_cash_flow";
    protected $guarded = ['id'];


    /**
     * 代理机构 拥有的登录用户
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */

    public function users()
    {
        return $this->hasMany('App\User', 'agent_id', 'id');
    }


}