<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Warehouse Admin
    |--------------------------------------------------------------------------
    |
    | The user who receives approved checkout requests and performs the
    | actual checkout. Overridable via .env so the role can be reassigned
    | without touching application code.
    |
    */

    'warehouse_admin_id' => (int) env('CHECKOUT_WAREHOUSE_ADMIN_ID', 40),

];
