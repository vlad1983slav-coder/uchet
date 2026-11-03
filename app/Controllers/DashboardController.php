<?php

declare(strict_types=1);

namespace Uchet\Controllers;

use Uchet\Core\Controller;

/**
 * Главная страница. Пока заглушка — полноценная панель управления
 * с остатками/документами будет реализована в п. 5–6 ТЗ.
 */
final class DashboardController extends Controller
{
    public function index(): void
    {
        $user = $this->user();
        $this->render('dashboard.index', [
            'title' => 'Главная',
            'userName' => $user['full_name'] ?? '',
            'roleCode' => $user['role_code'] ?? '',
        ], null);
    }
}
