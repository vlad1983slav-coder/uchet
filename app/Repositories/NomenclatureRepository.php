<?php

declare(strict_types=1);

namespace Uchet\Repositories;

use Uchet\Core\Repository;
use Uchet\Models\Nomenclature;

/** Справочник «Номенклатура». */
final class NomenclatureRepository extends Repository
{
    public function __construct()
    {
        parent::__construct('catalogs_nomenclature', Nomenclature::class);
    }

    /** Поиск для автодополнения в ТЧ документа: по артикулу или наименованию. */
    public function search(string $q, int $limit = 20): array
    {
        return $this->query(
            'SELECT n.*, u.name AS unit_name, u.code AS unit_code, c.name AS category_name
             FROM catalogs_nomenclature n
             LEFT JOIN catalogs_units u ON u.id = n.unit_id AND u.deleted_at IS NULL
             LEFT JOIN catalogs_categories c ON c.id = n.category_id AND c.deleted_at IS NULL
             WHERE n.deleted_at IS NULL AND (n.article LIKE ? OR n.name LIKE ?)
             ORDER BY n.article
             LIMIT ' . max(1, $limit),
            ['%' . $q . '%', '%' . $q . '%']
        );
    }

    /** Список с ед. измерения и категорией (для страницы справочника). */
    public function listWithRefs(int $page, int $perPage, string $q = '', string $sort = 'article', string $order = 'ASC'): array
    {
        if (!in_array($sort, ['id', 'article', 'name', 'purchase_price', 'min_stock', 'created_at'], true)) {
            $sort = 'article';
        }
        $likeSql = '';
        $params = [];
        if ($q !== '') {
            $likeSql = ' AND (n.article LIKE ? OR n.name LIKE ?)';
            $params = ['%' . $q . '%', '%' . $q . '%'];
        }
        $from = 'FROM catalogs_nomenclature n
                 LEFT JOIN catalogs_units u ON u.id = n.unit_id AND u.deleted_at IS NULL
                 LEFT JOIN catalogs_categories c ON c.id = n.category_id AND c.deleted_at IS NULL
                 WHERE 1=1 AND n.deleted_at IS NULL' . $likeSql;

        $countStmt = $this->pdo()->prepare('SELECT COUNT(*) ' . $from);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = 'SELECT n.*, u.name AS unit_name, u.code AS unit_code, c.name AS category_name '
            . $from
            . ' ORDER BY n.' . $this->q($sort) . ' ' . ($order === 'DESC' ? 'DESC' : 'ASC')
            . ' LIMIT ? OFFSET ?';
        $stmt = $this->pdo()->prepare($sql);
        $i = 1;
        foreach ($params as $value) {
            $stmt->bindValue($i++, $value);
        }
        $stmt->bindValue($i++, $perPage, \PDO::PARAM_INT);
        $stmt->bindValue($i, ($page - 1) * $perPage, \PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => array_map(fn (array $row) => Nomenclature::fromRow($row), $stmt->fetchAll()),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }
}
