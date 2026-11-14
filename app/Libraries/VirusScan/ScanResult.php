<?php

namespace App\Libraries\VirusScan;

enum ScanResult: string
{
    case Clean = 'clean';
    case Infected = 'infected';
    case Failed = 'failed';
}
