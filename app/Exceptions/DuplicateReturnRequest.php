<?php

namespace App\Exceptions;

use Exception;

/**
 * 同一资产同时只允许存在一条 pending 归还申请。
 */
class DuplicateReturnRequest extends Exception
{
    //
}
