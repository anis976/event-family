<?php

declare(strict_types=1);

namespace App\Util;

/**
 * Métadonnées de pagination pour les templates (composant _ef_paginate).
 */
final class Pagination
{
    /**
     * @return array{page: int, total_pages: int, total_items: int, per_page: int}
     */
    public static function create(int $requestedPage, int $totalItems, int $perPage): array
    {
        $perPage = max(1, $perPage);
        $totalItems = max(0, $totalItems);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $page = max(1, min($requestedPage, $totalPages));

        return [
            'page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $totalItems,
            'per_page' => $perPage,
        ];
    }
}
