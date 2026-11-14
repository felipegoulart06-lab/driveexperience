<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\ReferralProgram;
use App\Http\Request;
use App\Services\Settings;

final class PublicPageController
{
    public function indicacao(Request $request): void
    {
        view('public/indicacao', [
            'title' => 'Indique e ganhe',
            '_layout' => 'layouts/public.php',
            'offers' => (new ReferralProgram())->catalog(),
            'company' => (string) Settings::get('company_name', 'Drive Xperience'),
            'phone' => (string) Settings::get('company_phone', ''),
            'city' => (string) Settings::get('company_city', 'Balneário Camboriú'),
        ]);
    }

    public function aplicativo(Request $request): void
    {
        $play = trim((string) Settings::get('play_store_url', ''));
        view('public/aplicativo', [
            'title' => 'Aplicativo',
            '_layout' => 'layouts/public.php',
            'company' => (string) Settings::get('company_name', 'Drive Xperience'),
            'play' => preg_match('#^https://#', $play) ? $play : '',
        ]);
    }
}
