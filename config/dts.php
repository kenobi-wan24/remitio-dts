<?php

/*
| Remitio & Remitio DTS settings (R3 — roles & access control).
| Change in .env, then run: php artisan config:clear
*/
return [

    /*
    | Who may see records that are NOT marked confidential:
    |
    |   open      Everyone with an account (how the office works today).
    |             Records marked CONFIDENTIAL are limited to the people assigned to them.
    |
    |   assigned  Lawyers and staff see ONLY records assigned to them
    |             (handling lawyer, current holder, whoever recorded or handled it).
    |
    | The Administrator (Lawyer/Owner) always sees everything.
    */
    'access_mode' => env('DTS_ACCESS_MODE', 'open'),

    /*
    | R4 — optional deadlines. The firm asked us to remove due dates (Oct 2), so this is OFF.
    | Set DTS_DEADLINES=true to show a "Deadline" field and use it in the priority queue.
    */
    'deadlines' => (bool) env('DTS_DEADLINES', false),

];
