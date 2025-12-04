<?php
namespace Nwaneri\PaymentsRouter\Facades;

use Illuminate\Support\Facades\Facade;

class Payment extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Nwaneri\PaymentsRouter\PaymentRouter::class;
    }
}
