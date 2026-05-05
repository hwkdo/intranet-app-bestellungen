<?php

namespace Hwkdo\IntranetAppBestellungen\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Hwkdo\IntranetAppBestellungen\IntranetAppBestellungen
 */
class IntranetAppBestellungen extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Hwkdo\IntranetAppBestellungen\IntranetAppBestellungen::class;
    }
}
