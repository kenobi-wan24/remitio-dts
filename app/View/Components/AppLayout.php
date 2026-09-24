<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * Usage: <x-app-layout title="Clients"> ... </x-app-layout>
     */
    public function __construct(public ?string $title = null)
    {
    }

    public function render(): View
    {
        return view('layouts.app');
    }
}
